<?php

namespace Crm\UsersModule\Auth\Sso;

use Crm\ApplicationModule\Config\Repository\ConfigsRepository;
use Crm\ApplicationModule\DataProvider\DataProviderManager;
use Crm\UsersModule\DataProvider\GoogleSignInDataProviderInterface;
use Crm\UsersModule\Repository\UserConnectedAccountsRepository;
use Google_Client;
use Google_Service_Oauth2;
use Nette\Database\Table\IRow;
use Nette\Http\Session;
use Nette\Security\User;

class GoogleSignIn
{
    public const ACCESS_TOKEN_SOURCE_WEB_GOOGLE_SSO = 'web+google_sso';

    public const USER_SOURCE_GOOGLE_SSO = "google_sso";

    public const USER_GOOGLE_REGISTRATION_CHANNEL = "google";

    private const SESSION_SECTION = 'google_sign_in';

    // Default scopes MUST be included for OpenID Connect.
    private const DEFAULT_SCOPES =  [
        'email',
    ];

    private ?string $clientId;

    private ?string $clientSecret;
    
    private Session $session;
    
    private ConfigsRepository $configsRepository;

    private SsoUserManager $ssoUserManager;

    private User $user;

    private DataProviderManager $dataProviderManager;
    
    private ?Google_Client $googleClient = null;

    public function __construct(
        ?string $clientId,
        ?string $clientSecret,
        ConfigsRepository $configsRepository,
        Session $session,
        SsoUserManager $ssoUserManager,
        User $user,
        DataProviderManager $dataProviderManager
    ) {
        $this->configsRepository = $configsRepository;
        $this->session = $session;
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->ssoUserManager = $ssoUserManager;
        $this->user = $user;
        $this->dataProviderManager = $dataProviderManager;
    }

    public function isEnabled(): bool
    {
        return (boolean) ($this->configsRepository->loadByName('google_sign_in_enabled')->value ?? false);
    }

    public function setGoogleClient(Google_Client $googleClient): void
    {
        $this->googleClient = $googleClient;
    }

    /**
     * Implements validation of ID token (JWT token) as described in:
     * https://developers.google.com/identity/sign-in/web/backend-auth
     *
     * If token is successfully verified, user with Google connected account will be created (or matched to an existing user).
     * Note: Access token is not automatically created
     *
     * @param string      $idToken
     * @param string|null $gsiAccessToken
     *
     * @return IRow|null created/matched user
     * @throws AlreadyLinkedAccountSsoException
     * @throws \Crm\ApplicationModule\DataProvider\DataProviderException
     */
    public function signInUsingIdToken(string $idToken, string $gsiAccessToken = null): ?IRow
    {
        if (!$this->isEnabled()) {
            throw new \Exception('Google Sign In is not enabled, please see authentication configuration in your admin panel.');
        }

        $client = $this->getClient();
        $payload = $client->verifyIdToken($idToken);
        if (!$payload) {
            return null;
        }

        $userEmail = $payload['email'];
            // 'sub' represents Google ID in id_token
            //
            // Note: A Google account's email address can change, so don't use it to identify a user.
            // Instead, use the account's ID, which you can get on the client with getBasicProfile().getId(),
            // and on the backend from the sub claim of the ID token.
            // https://developers.google.com/identity/sign-in/web/people
        $googleUserId = $payload['sub'];

        $matchedUser = $this->ssoUserManager->matchUser(UserConnectedAccountsRepository::TYPE_GOOGLE_SIGN_IN, $googleUserId, $userEmail);

        /** @var GoogleSignInDataProviderInterface[] $providers */
        $providers = $this->dataProviderManager->getProviders('users.dataprovider.google_sign_in', GoogleSignInDataProviderInterface::class);
        foreach ($providers as $sorting => $provider) {
             $provider->provide([
                 'user' => $matchedUser,
                 'userEmail' => $userEmail,
                 'gsiAccessToken' => $gsiAccessToken,
             ]);
        }
        
        $userBuilder = $this->ssoUserManager->createUserBuilder(
            $userEmail,
            self::USER_SOURCE_GOOGLE_SSO,
            self::USER_GOOGLE_REGISTRATION_CHANNEL
        );

        // Match google user to CRM user
        return $this->ssoUserManager->matchOrCreateUser(
            $googleUserId,
            $userEmail,
            UserConnectedAccountsRepository::TYPE_GOOGLE_SIGN_IN,
            $userBuilder,
            $payload,
        );
    }
    
    /**
     * Exchanges one-time auth code for credentials, containing id_token, access_token, ...
     * Useful e.g. for offline access for users logged in apps.
     * See:
     * - https://developers.google.com/identity/sign-in/android/offline-access
     * - https://developers.google.com/identity/sign-in/ios/offline-access
     *
     * @param string      $gsiAuthCode
     * @param string      $redirectUri redirectUri depends on how auth_code was initially requested.
     *                                 In case of web surface, one may use 'postmessage' redirectUri,
     *                                 which is a reserved URI string in Google-land.
     *                                 Otherwise, use standard callback URI registered for OAuth client.
     *
     * @return array keys 'access_token', 'scope', 'id_token', 'token_type', 'refresh_token', 'expires_in', 'created'
     * @throws \Exception
     */
    public function exchangeAuthCode(string $gsiAuthCode, string $redirectUri): array
    {
        $client = $this->getClient($redirectUri);
        return $client->fetchAccessTokenWithAuthCode($gsiAuthCode);
    }

    /**
     * First step of OAuth2 authorization flow
     * Method returns url to redirect to and sets 'state' to verify later in callback
     * @param string $redirectUri
     *
     * @return string
     * @throws SsoException
     */
    public function signInRedirect(string $redirectUri, string $source = null): string
    {
        if (!$this->isEnabled()) {
            throw new \Exception('Google Sign In is not enabled, please see authentication configuration in your admin panel.');
        }

        if (isset($_GET['code'])) {
            throw new SsoException("Invalid call, 'code' GET parameter should be passed to redirect URI link");
        }

        $client = $this->getClient($redirectUri);

        // Parameters are set according to required parameters in documentation
        // https://github.com/googleapis/google-api-php-client/blob/master/docs/oauth-web.md#Step-1-Set-authorization-parameters
        $client->setScopes(self::DEFAULT_SCOPES);
        $client->setAccessType('online'); //without refresh-token (alternative is 'offline', with refresh token)

        // State is created according to documentation
        // https://developers.google.com/identity/protocols/oauth2/openid-connect#createxsrftoken
        $state = bin2hex(random_bytes(128/8));
        $client->setState($state);

        // save state to session for later verification
        $sessionSection = $this->session->getSection(self::SESSION_SECTION);
        $sessionSection->oauth2state = $state;
        $sessionSection->loggedUserId = $this->user->isLoggedIn() ? $this->user->getId() : null;
        $sessionSection->source = $source;

        return $client->createAuthUrl();
    }


    /**
     * Second step OAuth authorization flow
     * If callback data is successfully verified, user with Google connected account will be created (or matched to an existing user).
     *
     * Note: Access token is not automatically created
     *
     * @param string      $redirectUri
     * @param string|null $referer to save with user if user is created
     *
     * @return IRow user row
     * @throws AlreadyLinkedAccountSsoException if connected account is used
     * @throws SsoException if authentication fails
     * @throws \Crm\ApplicationModule\DataProvider\DataProviderException
     */
    public function signInCallback(string $redirectUri, string $referer = null): IRow
    {
        if (!$this->isEnabled()) {
            throw new \Exception('Google Sign In is not enabled, please see authentication configuration in your admin panel.');
        }

        if (!empty($_GET['error'])) {
            // Got an error, probably user denied access
            throw new SsoException('Google SignIn error: ' . htmlspecialchars($_GET['error']));
        }

        if (empty($_GET['code'])) {
            throw new SsoException('Google SignIn error: missing code');
        }

        $sessionSection = $this->session->getSection(self::SESSION_SECTION);

        // Check internal state
        if (empty($_GET['state']) || ($_GET['state'] !== $sessionSection->oauth2state)) {
            // State is invalid, possible CSRF attack in progress
            unset($sessionSection->oauth2state);
            throw new SsoException('Google SignIn error: invalid state');
        }

        // Check user state
        $loggedUserId = $this->user->isLoggedIn() ? $this->user->getId() : null;
        if ($loggedUserId !== $sessionSection->loggedUserId) {
            // State is invalid, possible user change between login request and callback
            unset($sessionSection->loggedUserId);
            throw new SsoException('Google SignIn error: invalid user state');
        }

        // Get OAuth access token
        $client = $this->getClient($redirectUri);
        $client->fetchAccessTokenWithAuthCode($_GET['code']);

        // Get user details using access token
        $service = new Google_Service_Oauth2($client);
        try {
            $userInfo = $service->userinfo->get();
        } catch (\Google_Service_Exception $e) {
            throw new SsoException('Google SignIn error: unable to retrieve user info', $e->getCode(), $e);
        }

        $userEmail =  $userInfo->getEmail();
            // 'sub' represents Google ID in id_token
            //
            // Note: A Google account's email address can change, so don't use it to identify a user.
            // Instead, use the account's ID, which you can get on the client with getBasicProfile().getId(),
            // and on the backend from the sub claim of the ID token.
            // https://developers.google.com/identity/sign-in/web/people
        $googleUserId =  $userInfo->getId();

        $matchedUser = $this->ssoUserManager->matchUser(UserConnectedAccountsRepository::TYPE_GOOGLE_SIGN_IN, $googleUserId, $userEmail);

        /** @var GoogleSignInDataProviderInterface[] $providers */
        $providers = $this->dataProviderManager->getProviders('users.dataprovider.google_sign_in', GoogleSignInDataProviderInterface::class);
        foreach ($providers as $sorting => $provider) {
             $provider->provide([
                 'user' => $matchedUser,
                 'gsiAccessToken' => $client->getAccessToken()['access_token'],
                 'userEmail' => $userEmail
             ]);
        }

        $userBuilder = $this->ssoUserManager->createUserBuilder(
            $userEmail,
            $sessionSection->source ?? self::USER_SOURCE_GOOGLE_SSO,
            self::USER_GOOGLE_REGISTRATION_CHANNEL,
            $referer
        );
        
        // Match google user to CRM user
        return $this->ssoUserManager->matchOrCreateUser(
            $googleUserId,
            $userEmail,
            UserConnectedAccountsRepository::TYPE_GOOGLE_SIGN_IN,
            $userBuilder,
            $userInfo->toSimpleObject(),
            $loggedUserId,
        );
    }

    private function getClient(?string $redirectUri = null): Google_Client
    {
        if ($this->googleClient) {
            return $this->googleClient;
        }
        
        if (!$this->clientId || !$this->clientSecret) {
            throw new \Exception("Google Sign In Client ID and Secret not configured, please configure 'users.sso.google' parameter based on the README file.");
        }

        $googleClient = new Google_Client([
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
        ]);
        if ($redirectUri) {
            $googleClient->setRedirectUri($redirectUri);
        }
        return $googleClient;
    }
}
