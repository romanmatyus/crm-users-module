<?php

namespace Crm\UsersModule\Auth\Sso;

use Crm\ApplicationModule\Config\Repository\ConfigsRepository;
use Crm\UsersModule\Repository\UserConnectedAccountsRepository;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use GuzzleHttp\Client;
use Nette\Database\Table\IRow;
use Nette\Http\Session;
use Nette\Http\Url;
use Nette\Security\User;
use Nette\Utils\Json;

class AppleSignIn
{
    public const ACCESS_TOKEN_SOURCE_WEB_APPLE_SSO = 'web+apple_sso';

    private const SESSION_SECTION = 'apple_sign_in';

    public const USER_SOURCE_APPLE_SSO = "apple_sso";

    public const USER_APPLE_REGISTRATION_CHANNEL = "apple";

    private $clientId;

    private $trustedClientIds;

    private $configsRepository;

    private $session;

    private $ssoUserManager;

    private $user;

    public function __construct(
        ?string $clientId,
        array $trustedClientIds,
        ConfigsRepository $configsRepository,
        Session $session,
        SsoUserManager $ssoUserManager,
        User $user
    ) {
        $this->clientId = $clientId;
        $this->trustedClientIds = array_flip(array_merge([$clientId], array_filter($trustedClientIds)));
        $this->configsRepository = $configsRepository;
        $this->session = $session;
        $this->ssoUserManager = $ssoUserManager;
        $this->user = $user;
    }

    public function isEnabled(): bool
    {
        return (boolean)($this->configsRepository->loadByName('apple_sign_in_enabled')->value ?? false);
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


        // save state and nonce to session for later verification
        $sessionSection = $this->session->getSection(self::SESSION_SECTION);
        $sessionSection->oauth2state = $state;
        $sessionSection->nonce = $nonce;
        $sessionSection->loggedUserId = $this->user->isLoggedIn() ? $this->user->getId() : null;
        $sessionSection->source = $source;

        return $url->getAbsoluteUrl();
    }

    /**
     * Second step OAuth authorization flow
     * If callback data is successfully verified, user with Apple connected account will be created (or matched to an existing user).
     *
     * Note: Access token is not automatically created
     *
     * @return IRow user row
     * @throws AlreadyLinkedAccountSsoException if connected account is used
     * @throws SsoException if authentication fails
     */
    public function signInCallback(): IRow
    {
        if (!$this->isEnabled()) {
            throw new \Exception('Apple Sign In is not enabled, please see authentication configuration in your admin panel.');
        }

        if (!empty($_POST['error'])) {
            // Got an error, probably user denied access
            throw new SsoException('Apple SignIn error: ' . htmlspecialchars($_POST['error']));
        }

        $sessionSection = $this->session->getSection(self::SESSION_SECTION);

        // Check internal state
        if ($_POST['state'] !== $sessionSection->oauth2state) {
            // State is invalid, possible CSRF attack in progress
            unset($sessionSection->oauth2state);
            throw new SsoException('Apple SignIn error: invalid state');
        }

        // Check user state
        $loggedUserId = $this->user->isLoggedIn() ? $this->user->getId() : null;
        if ($loggedUserId !== $sessionSection->loggedUserId) {
            // State is invalid, possible user change between login request and callback
            unset($sessionSection->loggedUserId);
            throw new SsoException('Apple SignIn error: invalid user state');
        }

        try {
            $idToken = $this->decodeIdToken($_POST['id_token']);
        } catch (\Exception $exception) {
            throw new SsoException('Apple SignIn error: unable to verify id token');
        }

        // Check id token
        if (!$this->isIdTokenValid($idToken, $sessionSection->nonce)) {
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

        // Match apple user to CRM user
        return $this->ssoUserManager->matchOrCreateUser(
            $appleUserId,
            $userEmail,
            UserConnectedAccountsRepository::TYPE_APPLE_SIGN_IN,
            $sessionSection->source ?? self::USER_SOURCE_APPLE_SSO,
            null,
            $loggedUserId,
            self::USER_APPLE_REGISTRATION_CHANNEL
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
     * @return IRow|null created/matched user
     * @throws \Exception
     */
    public function signInUsingIdToken(string $idTokenInput): ?IRow
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
        return $this->ssoUserManager->matchOrCreateUser(
            $appleUserId,
            $userEmail,
            UserConnectedAccountsRepository::TYPE_APPLE_SIGN_IN,
            self::USER_SOURCE_APPLE_SSO
        );
    }

    private function decodeIdToken($idToken)
    {
        $client = new Client();
        $response = $client->get('https://appleid.apple.com/auth/keys');
        $response = JSON::decode($response->getBody()->getContents(), true);

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
