<?php

namespace Crm\UsersModule\Auth\Sso;

use Crm\ApplicationModule\Config\Repository\ConfigsRepository;
use Crm\ApplicationModule\DataProvider\DataProviderException;
use Crm\ApplicationModule\DataProvider\DataProviderManager;
use Crm\ApplicationModule\RedisClientFactory;
use Crm\ApplicationModule\RedisClientTrait;
use Crm\UsersModule\DataProvider\GoogleSignInDataProviderInterface;
use Crm\UsersModule\Repository\UserConnectedAccountsRepository;
use Google\Client;
use Google\Service\Oauth2;
use Nette\Database\Table\ActiveRow;
use Nette\Http\Request;
use Nette\Http\Response;
use Nette\Security\User;
use Nette\Utils\Json;

class GoogleSignIn
{
    use RedisClientTrait;

    public const ACCESS_TOKEN_SOURCE_WEB_GOOGLE_SSO = 'web+google_sso';
    public const USER_SOURCE_GOOGLE_SSO = "google_sso";
    public const USER_GOOGLE_REGISTRATION_CHANNEL = "google";

    private const REDIS_GSI_KEY = 'gsi_data';

    // Default scopes MUST be included for OpenID Connect.
    private const DEFAULT_SCOPES =  [
        'email',
    ];

    private ?string $clientId;
    private ?string $clientSecret;
    private ?Client $googleClient = null;

    public function __construct(
        ?string $clientId,
        ?string $clientSecret,
        private ConfigsRepository $configsRepository,
        private SsoUserManager $ssoUserManager,
        private User $user,
        private DataProviderManager $dataProviderManager,
        private Response $response,
        private Request $request,
        RedisClientFactory $redisClientFactory
    ) {
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->redisClientFactory = $redisClientFactory;
    }

    public function isEnabled(): bool
    {
        return (boolean) ($this->configsRepository->loadByName('google_sign_in_enabled')->value ?? false);
    }

    public function setGoogleClient(Client $googleClient): void
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
     * @param string $idToken
     * @param string|null $gsiAccessToken
     * @param int|null $loggedUserId
     * @param string|null $source
     * @param string|null $locale if user is created, this locale will be set as a default user locale
     * @return ActiveRow|null created/matched user
     * @throws AlreadyLinkedAccountSsoException
     * @throws DataProviderException
     */
    public function signInUsingIdToken(
        string $idToken,
        string $gsiAccessToken = null,
        int $loggedUserId = null,
        string $source = null,
        ?string $locale = null
    ): ?ActiveRow {
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

        // Match only already connected accounts (DO NOT provide email here) before any external matching (via data provider) is done
        $matchedUser = $this->ssoUserManager->matchUser(UserConnectedAccountsRepository::TYPE_GOOGLE_SIGN_IN, $googleUserId);

        /** @var GoogleSignInDataProviderInterface[] $providers */
        $providers = $this->dataProviderManager->getProviders('users.dataprovider.google_sign_in', GoogleSignInDataProviderInterface::class);
        foreach ($providers as $sorting => $provider) {
             $provider->provide([
                 'matchedUser' => $matchedUser,
                 'googleUserEmail' => $userEmail,
                 'googleUserId' => $googleUserId,
                 'gsiAccessToken' => $gsiAccessToken,
                 'locale' => $locale,
             ]);
        }

        $userBuilder = $this->ssoUserManager->createUserBuilder(
            $userEmail,
            $source ?? self::USER_SOURCE_GOOGLE_SSO,
            self::USER_GOOGLE_REGISTRATION_CHANNEL
        );

        if ($locale) {
            $userBuilder->setLocale($locale);
        }

        // Match google user to CRM user
        return $this->ssoUserManager->matchOrCreateUser(
            $googleUserId,
            $userEmail,
            UserConnectedAccountsRepository::TYPE_GOOGLE_SIGN_IN,
            $userBuilder,
            $payload,
            $loggedUserId
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
        return $this->getClient($redirectUri)->fetchAccessTokenWithAuthCode($gsiAuthCode);
    }

    /**
     * First step of OAuth2 authorization flow
     * Method returns url to redirect to and sets 'state' to verify later in callback
     *
     * @param string      $redirectUri
     * @param string|null $source
     *
     * @return string
     * @throws SsoException
     */
    public function signInRedirect(string $redirectUri, string $source = null): string
    {
        if (!$this->isEnabled()) {
            throw new \Exception('Google Sign In is not enabled, please see authentication configuration in your admin panel.');
        }

        if (!empty($this->request->getQuery('code'))) {
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

        $userId = $this->user->isLoggedIn() ? $this->user->getId() : null;

        // Each state has a separate Redis key to use expiration feature
        // Redis does not support expiration on individual hash fields
        $this->redis()->hset($this->redisKey($state), 'json', Json::encode(array_filter([
            'source' => $source ?? null,
            'user_id' => $userId ?? null,
        ])));

        // expiration max 10 minutes
        $this->redis()->expire($this->redisKey($state), 10*60);

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
     * @param string|null $locale
     *
     * @return ActiveRow user row
     * @throws AlreadyLinkedAccountSsoException if connected account is used
     * @throws SsoException if authentication fails
     * @throws DataProviderException
     */
    public function signInCallback(string $redirectUri, ?string $referer = null, ?string $locale = null): ActiveRow
    {
        if (!$this->isEnabled()) {
            throw new \Exception('Google Sign In is not enabled, please see authentication configuration in your admin panel.');
        }

        if (!empty($this->request->getQuery('error'))) {
            // Got an error, probably user denied access
            throw new SsoException('Google SignIn error: ' . htmlspecialchars($this->request->getQuery('error')));
        }

        if (empty($this->request->getQuery('code'))) {
            throw new SsoException('Google SignIn error: missing code');
        }

        // Check state validity (to avoid CSRF attack)
        if (empty($this->request->getQuery('state'))) {
            throw new SsoException("Google SignIn error: missing state variable");
        }
        $state = $this->request->getQuery('state');
        $savedStateString = $this->redis()->hget($this->redisKey($state), 'json');
        $this->redis()->hdel($this->redisKey($state), ['json']);
        if (!$savedStateString) {
            throw new SsoException("Google SignIn error: invalid state '$state'");
        }
        $savedState = Json::decode($savedStateString, Json::FORCE_ARRAY);

        // Check that same user triggered login as is currently signed-in
        $loggedUserId = $this->user->isLoggedIn() ? $this->user->getId() : null;
        $savedStateUserId = $savedState['user_id'] ?? null;

        if ($savedStateUserId !== $loggedUserId) {
            // State is invalid, possible user change between login request and callback
            throw new SsoException('Google SignIn error: invalid user state (current userId: '. $loggedUserId . ', state userId: ' . $savedStateUserId . ')');
        }

        // Get OAuth access token
        $client = $this->getClient($redirectUri);
        $client->fetchAccessTokenWithAuthCode($this->request->getQuery('code'));

        // Get user details using access token
        $service = new Oauth2($client);
        try {
            $userInfo = $service->userinfo->get();
        } catch (\Google\Service\Exception $e) {
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

        // Match only already connected accounts (DO NOT provide email here) before any external matching (via data provider) is done
        $matchedUser = $this->ssoUserManager->matchUser(UserConnectedAccountsRepository::TYPE_GOOGLE_SIGN_IN, $googleUserId);

        /** @var GoogleSignInDataProviderInterface[] $providers */
        $providers = $this->dataProviderManager->getProviders('users.dataprovider.google_sign_in', GoogleSignInDataProviderInterface::class);
        foreach ($providers as $sorting => $provider) {
             $provider->provide([
                 'matchedUser' => $matchedUser,
                 'googleUserEmail' => $userEmail,
                 'googleUserId' => $googleUserId,
                 'gsiAccessToken' => $client->getAccessToken()['access_token'],
                 'locale' => $locale,
             ]);
        }

        $userBuilder = $this->ssoUserManager->createUserBuilder(
            $userEmail,
            $savedState['source'] ?? self::USER_SOURCE_GOOGLE_SSO,
            self::USER_GOOGLE_REGISTRATION_CHANNEL,
            $referer
        );

        if ($locale) {
            $userBuilder->setLocale($locale);
        }

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

    private function getClient(?string $redirectUri = null): Client
    {
        if ($this->googleClient) {
            return $this->googleClient;
        }

        if (!$this->clientId || !$this->clientSecret) {
            throw new \Exception("Google Sign In Client ID and Secret not configured, please configure 'users.sso.google' parameter based on the README file.");
        }

        $googleClient = new Client([
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
        ]);
        if ($redirectUri) {
            $googleClient->setRedirectUri($redirectUri);
        }
        return $googleClient;
    }

    private function redisKey(string $state): string
    {
        return self::REDIS_GSI_KEY . '_' . $state;
    }
}
