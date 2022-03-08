<?php

namespace Crm\UsersModule\Auth\Sso;

use Crm\ApplicationModule\Config\Repository\ConfigsRepository;
use Crm\ApplicationModule\Request as CrmRequest;
use Crm\UsersModule\Repository\UserConnectedAccountsRepository;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use GuzzleHttp\Client;
use Nette\Database\Table\ActiveRow;
use Nette\Http\Request;
use Nette\Http\Response;
use Nette\Http\Url;
use Nette\Security\User;
use Nette\Utils\Json;

class AppleSignIn
{
    public const ACCESS_TOKEN_SOURCE_WEB_APPLE_SSO = 'web+apple_sso';

    public const USER_SOURCE_APPLE_SSO = "apple_sso";

    public const USER_APPLE_REGISTRATION_CHANNEL = "apple";

    private const COOKIE_ASI_STATE = 'asi_state';

    private const COOKIE_ASI_SOURCE = 'asi_source';

    private const COOKIE_ASI_USER_ID = 'asi_user_id';

    private const COOKIE_ASI_NONCE = 'asi_nonce';

    private $clientId;

    private $trustedClientIds = [];

    private $configsRepository;

    private $ssoUserManager;

    private $user;

    private Response $response;

    private Request $request;

    public function __construct(
        ?string $clientId,
        array $trustedClientIds,
        ConfigsRepository $configsRepository,
        SsoUserManager $ssoUserManager,
        User $user,
        Response $response,
        Request $request
    ) {
        $this->clientId = $clientId;
        $this->configsRepository = $configsRepository;
        $this->ssoUserManager = $ssoUserManager;
        $this->user = $user;
        $this->response = $response;
        $this->request = $request;

        if ($clientId !== null) {
            $this->trustedClientIds[$clientId] = true;
        }
        foreach (array_filter($trustedClientIds) as $trustedClientId) {
            $this->trustedClientIds[$trustedClientId] = true;
        }
    }

    public function isEnabled(): bool
    {
        return (boolean)($this->configsRepository->loadByName('apple_sign_in_enabled')->value ?? false);
    }

    private function setLoginCookie(string $key, $value)
    {
        $this->response->setCookie(
            $key,
            $value,
            strtotime('+1 hour'),
            '/',
            CrmRequest::getDomain(),
            true,
            true,
            'None' // Lax cannot be used with POST request (response from Apple is POST)
        );
    }

    /**
     * First step of OAuth2 authorization flow
     * Method returns url to redirect to and sets 'state' and 'nonce' to verify later in callback
     * @param string $redirectUri
     *
     * @return string
     * @throws SsoException
     */
    public function signInRedirect(string $redirectUri, string $source = null): string
    {
        if (!$this->isEnabled()) {
            throw new \Exception('Apple Sign In is not enabled, please see authentication configuration in your admin panel.');
        }

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

        //save cookie for later verification
        $this->setLoginCookie(self::COOKIE_ASI_STATE, $state);
        if ($source) {
            $this->setLoginCookie(self::COOKIE_ASI_SOURCE, $source);
        } else {
            $this->response->deleteCookie(self::COOKIE_ASI_SOURCE);
        }
        $this->setLoginCookie(self::COOKIE_ASI_NONCE, $nonce);

        $userId = $this->user->isLoggedIn() ? $this->user->getId() : null;
        if ($userId) {
            $this->setLoginCookie(self::COOKIE_ASI_USER_ID, $userId);
        } else {
            $this->response->deleteCookie(self::COOKIE_ASI_USER_ID);
        }

        return $url->getAbsoluteUrl();
    }

    /**
     * Second step OAuth authorization flow
     * If callback data is successfully verified, user with Apple connected account will be created (or matched to an existing user).
     *
     * Note: Access token is not automatically created
     *
     * @param string|null $referer to save with user if user is created
     *
     * @return ActiveRow user row
     * @throws AlreadyLinkedAccountSsoException if connected account is used
     * @throws SsoException if authentication fails
     */
    public function signInCallback(string $referer = null): ActiveRow
    {
        $asiState = $this->request->getCookie(self::COOKIE_ASI_STATE);
        $asiUserId = $this->request->getCookie(self::COOKIE_ASI_USER_ID);
        $asiSource = $this->request->getCookie(self::COOKIE_ASI_SOURCE);
        $asiNonce = $this->request->getCookie(self::COOKIE_ASI_NONCE);

        $this->response->deleteCookie(self::COOKIE_ASI_STATE);
        $this->response->deleteCookie(self::COOKIE_ASI_USER_ID);
        $this->response->deleteCookie(self::COOKIE_ASI_SOURCE);
        $this->response->deleteCookie(self::COOKIE_ASI_NONCE);

        if (!$this->isEnabled()) {
            throw new \Exception('Apple Sign In is not enabled, please see authentication configuration in your admin panel.');
        }

        if (!empty($_POST['error'])) {
            // Got an error, probably user denied access
            throw new SsoException('Apple SignIn error: ' . htmlspecialchars($_POST['error']));
        }

        // Check internal state
        if ($_POST['state'] !== $asiState) {
            // State is invalid, possible CSRF attack in progress
            throw new SsoException('Apple SignIn error: invalid state');
        }

        // Check user state
        $loggedUserId = $this->user->isLoggedIn() ? $this->user->getId() : null;
        if ((string) $loggedUserId !== (string) $asiUserId) {
            // State is invalid, possible user change between login request and callback
            throw new SsoException('Apple SignIn error: invalid user state');
        }

        try {
            $idToken = $this->decodeIdToken($_POST['id_token']);
        } catch (\Exception $exception) {
            throw new SsoException('Apple SignIn error: unable to verify id token');
        }

        // Check id token
        if (!$this->isIdTokenValid($idToken, $asiNonce)) {
            // Id token is invalid
            throw new SsoException('Apple SignIn error: invalid id token');
        }

        // Check code
        if (!$this->isCodeValid($_POST['code'], $idToken)) {
            // Code is invalid
            throw new SsoException('Apple SignIn error: invalid code');
        }

        $userEmail = $idToken->email;
        // 'sub' represents Apple ID in id_token
        // Note: Use 'sub' to identify users, email can change or be private
        $appleUserId = $idToken->sub;

        $userBuilder = $this->ssoUserManager->createUserBuilder(
            $userEmail,
            $asiSource ?? self::USER_SOURCE_APPLE_SSO,
            self::USER_APPLE_REGISTRATION_CHANNEL,
            $referer
        );

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
     *
     * @return ActiveRow|null created/matched user
     * @throws \Exception
     */
    public function signInUsingIdToken(string $idTokenInput): ?ActiveRow
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

        return JWT::decode($idToken, JWK::parseKeySet($response), ['RS256']);
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

    private function isCodeValid($code, $idToken): bool
    {
        $hash = hash('sha256', $code, true);
        $firstHalfHash = substr($hash, 0, strlen($hash) / 2);

        return JWT::urlsafeB64Encode($firstHalfHash) === $idToken->c_hash;
    }
}
