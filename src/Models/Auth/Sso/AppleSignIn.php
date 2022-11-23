<?php

namespace Crm\UsersModule\Auth\Sso;

use Crm\ApplicationModule\Config\Repository\ConfigsRepository;
use Crm\ApplicationModule\RedisClientFactory;
use Crm\ApplicationModule\RedisClientTrait;
use Crm\UsersModule\Repository\UserConnectedAccountsRepository;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use GuzzleHttp\Client;
use Nette\Database\Table\ActiveRow;
use Nette\Http\Request;
use Nette\Http\Response;
use Nette\Http\Session;
use Nette\Http\Url;
use Nette\Security\User;
use Nette\Utils\Json;

class AppleSignIn
{
    use RedisClientTrait;

    public const ACCESS_TOKEN_SOURCE_WEB_APPLE_SSO = 'web+apple_sso';
    public const USER_SOURCE_APPLE_SSO = "apple_sso";
    public const USER_APPLE_REGISTRATION_CHANNEL = "apple";

    private const REDIS_ASI_KEY = 'asi_data';

    private ?string $clientId;
    private array $trustedClientIds = [];

    public function __construct(
        ?string $clientId,
        array $trustedClientIds,
        private ConfigsRepository $configsRepository,
        private SsoUserManager $ssoUserManager,
        private User $user,
        private Response $response,
        private Request $request,
        private Session $session,
        RedisClientFactory $redisClientFactory
    ) {
        $this->clientId = $clientId;

        if ($clientId !== null) {
            $this->trustedClientIds[$clientId] = true;
        }
        foreach (array_filter($trustedClientIds) as $trustedClientId) {
            $this->trustedClientIds[$trustedClientId] = true;
        }
        $this->redisClientFactory = $redisClientFactory;
    }

    public function isEnabled(): bool
    {
        return (boolean)($this->configsRepository->loadByName('apple_sign_in_enabled')->value ?? false);
    }

    /**
     * First step of OAuth2 authorization flow
     * Method returns url to redirect to and sets 'state' and 'nonce' to verify later in callback
     *
     * @param string      $redirectUri
     * @param string|null $source
     *
     * @return string
     * @throws \Exception
     */
    public function signInRedirect(string $redirectUri, string $source = null): string
    {
        if (!$this->isEnabled()) {
            throw new \Exception('Apple Sign In is not enabled, please see authentication configuration in your admin panel.');
        }

        // State is generated in a similar way as in GoogleSignIn
        $state = bin2hex(random_bytes(128 / 8));
        $nonce = bin2hex(random_bytes(128 / 8));

        $url = new Url('https://appleid.apple.com/auth/authorize');
        $url->setQuery([
            'client_id' => $this->clientId,
            'redirect_uri' => $redirectUri,
            'response_mode' => 'form_post',
            'response_type' => 'id_token code',
            'scope' => 'email',
            'state' => $state,
            'nonce' => $nonce
        ]);

        $userId = $this->user->isLoggedIn() ? $this->user->getId() : null;

        // Each state has a separate Redis key to use expiration feature
        // Redis does not support expiration on individual hash fields
        $this->redis()->hset($this->redisKey($state), 'json', Json::encode(array_filter([
            'nonce' => $nonce,
            'source' => $source ?? null,
            'user_id' => $userId ?? null,
        ])));

        // expiration max 10 minutes
        $this->redis()->expire($this->redisKey($state), 10*60);

        $this->setSessionCookieForCallback($redirectUri);

        return $url->getAbsoluteUrl();
    }

    /**
     * Second step OAuth authorization flow
     * If callback data is successfully verified, user with Apple connected account will be created (or matched to an existing user).
     *
     * Note: Access token is not automatically created
     *
     * @param string|null $referer to save with user if user is created
     * @param string|null $locale
     *
     * @return ActiveRow user row
     * @throws AlreadyLinkedAccountSsoException if connected account is used
     * @throws SsoException if authentication fails
     */
    public function signInCallback(?string $referer = null, ?string $locale = null): ActiveRow
    {

        if (!$this->isEnabled()) {
            throw new \Exception('Apple Sign In is not enabled, please see authentication configuration in your admin panel.');
        }

        if (!empty($this->request->getPost('error'))) {
            // Got an error, probably user denied access
            throw new SsoException('Apple SignIn error: ' . htmlspecialchars($this->request->getPost('error')));
        }

        // Check state validity (to avoid CSRF attack)
        if (empty($this->request->getPost('state'))) {
            throw new SsoException("Apple SignIn error: missing state variable");
        }
        $state = $this->request->getPost('state');
        $savedStateString = $this->redis()->hget($this->redisKey($state), 'json');
        $this->redis()->hdel($this->redisKey($state), ['json']);
        if (!$savedStateString) {
            throw new SsoException("Apple SignIn error: invalid state '$state'");
        }
        $savedState = Json::decode($savedStateString, Json::FORCE_ARRAY);

        // Check that same user triggered login as is currently signed-in
        $loggedUserId = $this->user->isLoggedIn() ? $this->user->getId() : null;
        $savedStateUserId = $savedState['user_id'] ?? null;

        if ($savedStateUserId !== $loggedUserId) {
            // State is invalid, possible user change between login request and callback
            throw new SsoException('Apple SignIn error: invalid user state (current userId: '. $loggedUserId . ', state userId: ' . $savedStateUserId . ')');
        }

        try {
            $idToken = $this->decodeIdToken($this->request->getPost('id_token'));
        } catch (\Exception $exception) {
            throw new SsoException('Apple SignIn error: unable to verify id token');
        }

        // Check id token
        if (!$this->isIdTokenValid($idToken, $savedState['nonce'] ?? null)) {
            // Id token is invalid
            throw new SsoException('Apple SignIn error: invalid id token');
        }

        // Check code
        if (!$this->isCodeValid($this->request->getPost('code'), $idToken)) {
            // Code is invalid
            throw new SsoException('Apple SignIn error: invalid code');
        }

        $userEmail = $idToken->email;
        // 'sub' represents Apple ID in id_token
        // Note: Use 'sub' to identify users, email can change or be private
        $appleUserId = $idToken->sub;

        $userBuilder = $this->ssoUserManager->createUserBuilder(
            $userEmail,
            $savedState['source'] ?? self::USER_SOURCE_APPLE_SSO,
            self::USER_APPLE_REGISTRATION_CHANNEL,
            $referer
        );

        if ($locale) {
            $userBuilder->setLocale($locale);
        }

        return $this->ssoUserManager->matchOrCreateUser(
            $appleUserId,
            $userEmail,
            UserConnectedAccountsRepository::TYPE_APPLE_SIGN_IN,
            $userBuilder,
            null,
            $loggedUserId
        );
    }

    /**
     * Implements validation of ID token (JWT token)
     *
     * If token is successfully verified, user with Apple connected account will be created (or matched to an existing user).
     * Note: Access token is not automatically created
     *
     * @param string $idTokenInput
     * @param string|null $locale if user is created, this locale will be set as a default user locale
     *
     * @return ActiveRow|null created/matched user
     * @throws \Exception
     */
    public function signInUsingIdToken(string $idTokenInput, ?string $locale = null): ?ActiveRow
    {
        if (!$this->isEnabled()) {
            throw new \Exception('Apple Sign In is not enabled, please see authentication configuration in your admin panel.');
        }

        if (!$this->clientId) {
            throw new \Exception("Apple Sign In Client ID not configured, please configure 'users.sso.apple' parameter based on the README file.");
        }

        try {
            $idToken = $this->decodeIdToken($idTokenInput);
        } catch (\Exception $exception) {
            return null;
        }

        // Check id token
        if (!$this->isIdTokenValid($idToken)) {
            // Id token is invalid
            return null;
        }

        $userEmail = $idToken->email;
        // 'sub' represents Apple ID in id_token
        // Note: Use 'sub' to identify users, email can change or be private
        $appleUserId = $idToken->sub;

        // Match apple user to CRM user
        $userBuilder = $this->ssoUserManager->createUserBuilder(
            $userEmail,
            self::USER_SOURCE_APPLE_SSO,
            self::USER_APPLE_REGISTRATION_CHANNEL
        );

        if ($locale) {
            $userBuilder->setLocale($locale);
        }

        return $this->ssoUserManager->matchOrCreateUser(
            $appleUserId,
            $userEmail,
            UserConnectedAccountsRepository::TYPE_APPLE_SIGN_IN,
            $userBuilder
        );
    }

    private function decodeIdToken($idToken)
    {
        $client = new Client();
        $response = $client->get('https://appleid.apple.com/auth/keys');
        $response = Json::decode($response->getBody()->getContents(), Json::FORCE_ARRAY);

        // RS256 = openssl + SHA256
        return JWT::decode($idToken, JWK::parseKeySet($response, 'RS256'));
    }

    private function isIdTokenValid($idToken, $nonce = null): bool
    {
        if ($idToken->iss !== 'https://appleid.apple.com') {
            return false;
        }

        if (!isset($this->trustedClientIds[$idToken->aud])) {
            return false;
        }

        if ($idToken->exp < time()) {
            return false;
        }

        if ($idToken->nonce_supported) {
            if ($nonce && ($idToken->nonce ?? null) !== $nonce) {
                return false;
            }
        }

        return true;
    }

    /**
     * Sets short-lived session cookie having `SameSite: None` flag and callback URI path .
     *
     * This is required for session cookie to be sent along with callback (POST) request from Apple after successful SSO login.
     * Normal session cookie has `SameSite: Lax` flag and therefore is not sent along with POST (cross-origin) requests.
     *
     * Session is required to check if user that triggered OAuth flow is the same that we check against in the callback (see `user_id`).
     *
     * @param string $redirectUri
     *
     * @return void
     */
    private function setSessionCookieForCallback(string $redirectUri): void
    {
        $url = new Url($redirectUri);

        $cookie = session_get_cookie_params();
        $this->response->setCookie(
            $this->session->getName(),
            $this->session->getId(),
            strtotime('+10 minutes'), // this is short-lived session cookie
            $url->getPath(), // valid only for callback path
            $cookie['domain'],
            true, // "SameSite: None" requires secure to be set to "true"
            $cookie['httponly'],
            'None'
        );
    }

    private function isCodeValid($code, $idToken): bool
    {
        $hash = hash('sha256', $code, true);
        $firstHalfHash = substr($hash, 0, strlen($hash) / 2);

        return JWT::urlsafeB64Encode($firstHalfHash) === $idToken->c_hash;
    }

    private function redisKey(string $state): string
    {
        return self::REDIS_ASI_KEY . '_' . $state;
    }
}
