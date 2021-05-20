<?php

namespace Crm\UsersModule\Auth\Sso;

use Crm\ApplicationModule\Config\Repository\ConfigsRepository;
use Crm\UsersModule\Auth\UserManager;
use Crm\UsersModule\Builder\UserBuilder;
use Crm\UsersModule\Repository\UserConnectedAccountsRepository;
use Google_Client;
use Google_Service_Oauth2;
use Nette\Database\Context;
use Nette\Database\Table\IRow;
use Nette\Http\Session;

class GoogleSignIn
{
    public const ACCESS_TOKEN_SOURCE_WEB_GOOGLE_SSO = 'web+google_sso';

    private const USER_SOURCE_GOOGLE_SSO = "google_sso";

    private const SESSION_SECTION = 'google_sign_in';

    // Default scopes MUST be included for OpenID Connect.
    private const DEFAULT_SCOPES =  [
        'email',
    ];

    private $configsRepository;

    private $session;

    private $userBuilder;

    private $connectedAccountsRepository;

    private $clientId;

    private $clientSecret;

    private $dbContext;

    private $userManager;

    public function __construct(
        ?string $clientId,
        ?string $clientSecret,
        ConfigsRepository $configsRepository,
        Session $session,
        UserBuilder $userBuilder,
        UserConnectedAccountsRepository $connectedAccountsRepository,
        UserManager $userManager,
        Context $dbContext
    ) {
        $this->configsRepository = $configsRepository;
        $this->session = $session;
        $this->userBuilder = $userBuilder;
        $this->connectedAccountsRepository = $connectedAccountsRepository;
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->dbContext = $dbContext;
        $this->userManager = $userManager;
    }

    public function isEnabled(): bool
    {
        return (boolean) ($this->configsRepository->loadByName('google_sign_in_enabled')->value ?? false);
    }

    /**
     * Implements validation of ID token (JWT token) as described in:
     * https://developers.google.com/identity/sign-in/web/backend-auth
     *
     * If token is successfully verified, user with Google connected account will be created (or matched to an existing user).
     * Note: Access token is not automatically created
     *
     * @param string $idToken
     *
     * @return IRow|null created/matched user
     * @throws \Exception
     */
    public function signInUsingIdToken(string $idToken): ?IRow
    {
        if (!$this->isEnabled()) {
            throw new \Exception('Google Sign In is not enabled, please see authentication configuration in your admin panel.');
        }

        if (!$this->clientId) {
            throw new \Exception("Google Sign In Client ID and Secret not configured, please configure 'users.sso.google' parameter based on the README file.");
        }

        $client = $this->getClient();
        $payload = $client->verifyIdToken($idToken);
        if (!$payload) {
            return null;
        }

        $this->dbContext->beginTransaction();

        try {
            $userEmail = $payload['email'];
            // 'sub' represents Google ID in id_token
            //
            // Note: A Google account's email address can change, so don't use it to identify a user.
            // Instead, use the account's ID, which you can get on the client with getBasicProfile().getId(),
            // and on the backend from the sub claim of the ID token.
            // https://developers.google.com/identity/sign-in/web/people
            $googleUserId = $payload['sub'];

            $user = $this->userManager->matchSsoUser(
                UserConnectedAccountsRepository::TYPE_GOOGLE_SIGN_IN,
                $googleUserId,
                $userEmail
            );

            if (!$user) {
                // if user is not in our DB, create him/her
                // our access_token is not automatically created
                $user = $this->userBuilder->createNew()
                    ->setEmail($userEmail)
                    ->setPassword('', false) // Password will be empty, therefore unable to log-in
                    ->setPublicName($userEmail)
                    ->setRole('user')
                    ->setActive(true)
                    ->setIsInstitution(false)
                    ->setSource(self::USER_SOURCE_GOOGLE_SSO)
                    ->setAddTokenOption(false)
                    ->save();
            }

            $connectedAccount = $this->connectedAccountsRepository->getForUser($user, UserConnectedAccountsRepository::TYPE_GOOGLE_SIGN_IN);
            if (!$connectedAccount) {
                $this->connectedAccountsRepository->add(
                    $user,
                    UserConnectedAccountsRepository::TYPE_GOOGLE_SIGN_IN,
                    $googleUserId,
                    $userEmail,
                    $payload
                );
            }
        } catch (\Exception $e) {
            $this->dbContext->rollBack();
            throw $e;
        }
        $this->dbContext->commit();

        return $user;
    }

    /**
     * First step of OAuth2 authorization flow
     * Method returns url to redirect to and sets 'state' to verify later in callback
     * @param string $redirectUri
     *
     * @return string
     * @throws SsoException
     */
    public function signInRedirect(string $redirectUri): string
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

        return $client->createAuthUrl();
    }

    /**
     * Second step OAuth authorization flow
     * If callback data is successfully verified, user with Google connected account will be created (or matched to an existing user).
     *
     * Note: Access token is not automatically created
     *
     * @param string $redirectUri
     *
     * @return IRow user row
     * @throws SsoException if authentication fails
     */
    public function signInCallback(string $redirectUri): IRow
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

        // Check state
        if (empty($_GET['state']) || ($_GET['state'] !== $sessionSection->oauth2state)) {
            // State is invalid, possible CSRF attack in progress
            unset($sessionSection->oauth2state);
            throw new SsoException('Google SignIn error: invalid state');
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

        // Match google user to CRM user
        $this->dbContext->beginTransaction();
        try {
            $user = $this->userManager->matchSsoUser(
                UserConnectedAccountsRepository::TYPE_GOOGLE_SIGN_IN,
                $userInfo->getId(), // this represents Google ID, same as 'sub' in id_token
                $userInfo->getEmail()
            );
            if (!$user) {
                // if user is not in our DB, create him/her
                // our access_token is not automatically created
                $user = $this->userBuilder->createNew()
                    ->setEmail($userInfo->getEmail())
                    ->setPassword('', false) // Password will be empty, therefore unable to log-in
                    ->setPublicName($userInfo->getEmail())
                    ->setRole('user')
                    ->setActive(true)
                    ->setIsInstitution(false)
                    ->setSource(self::USER_SOURCE_GOOGLE_SSO)
                    ->setAddTokenOption(false)
                    ->save();
            }

            $connectedAccount = $this->connectedAccountsRepository->getForUser($user, UserConnectedAccountsRepository::TYPE_GOOGLE_SIGN_IN);
            if (!$connectedAccount) {
                $this->connectedAccountsRepository->add(
                    $user,
                    UserConnectedAccountsRepository::TYPE_GOOGLE_SIGN_IN,
                    $userInfo->getId(),
                    $userInfo->getEmail(),
                    $userInfo->toSimpleObject()
                );
            }
        } catch (\Exception $e) {
            $this->dbContext->rollBack();
            throw $e;
        }
        $this->dbContext->commit();

        return $user;
    }

    private function getClient(?string $redirectUri = null): Google_Client
    {
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
