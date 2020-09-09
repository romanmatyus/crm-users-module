<?php

namespace Crm\UsersModule\Tests;

use Crm\ApplicationModule\ApplicationManager;
use Crm\ApplicationModule\Tests\DatabaseTestCase;
use Crm\UsersModule\Auth\Access\AccessToken;
use Crm\UsersModule\Auth\AutoLogin\AutoLogin;
use Crm\UsersModule\Auth\UserAuthenticator;
use Crm\UsersModule\Auth\UserManager;
use Crm\UsersModule\Repository\AccessTokensRepository;
use Crm\UsersModule\Repository\LoginAttemptsRepository;
use Crm\UsersModule\Repository\UsersRepository;
use Nette\Database\Table\IRow;
use Nette\Security\AuthenticationException;
use Nette\Security\IAuthenticator;
use Nette\Utils\DateTime;

class UserAuthenticatorTest extends DatabaseTestCase
{
    /** @var UserAuthenticator */
    private $userAuthenticator;

    /** @var UserManager $userManager */
    private $userManager;

    /** @var UsersRepository $usersRepository */
    private $usersRepository;

    /** @var AccessTokensRepository $accessTokenRepository */
    private $accessTokenRepository;

    /** @var string $accessTokenLastVersion */
    private $accessTokenLastVersion;

    /** @var AutoLogin $autoLogin */
    private $autoLogin;

    /** @var IRow $user */
    private $user;

    /** @var IRow $admin */
    private $admin;

    private $testUserEmail = "test@test.test";
    private $testUserPassword = "nbusr123";
    private $testAdminEmail = "admin@test.test";
    private $testAdminPassword = "nbusr123";

    public function requiredRepositories(): array
    {
        return [
            UsersRepository::class,
            AccessTokensRepository::class,
            LoginAttemptsRepository::class,
        ];
    }

    public function requiredSeeders(): array
    {
        return [];
    }

    public function setUp(): void
    {
        parent::setUp();
        // ApplicationManager initialization required to register Authenticators
        $this->inject(ApplicationManager::class);

        $this->userAuthenticator = $this->inject(UserAuthenticator::class);
        $this->userManager = $this->inject(UserManager::class);
        $this->autoLogin = $this->inject(AutoLogin::class);

        $this->usersRepository = $this->getRepository(UsersRepository::class);
        $this->accessTokenRepository = $this->getRepository(AccessTokensRepository::class);

        /** @var \Crm\UsersModule\Auth\Access\AccessToken $accessToken */
        $accessToken = $this->inject(AccessToken::class);
        $this->accessTokenLastVersion = $accessToken->lastVersion();

        $this->getUser();
        $this->getAdmin();
    }


    /* **************************************************************
     * Username/Password API login
     * - makes sence only for code coverage (block setting $api), other scenarios are same as for username/password
     */

    public function testUsernamePasswordApi()
    {
        $userIdentity = $this->userAuthenticator->authenticate([
            'username' => $this->testUserEmail,
            'password' => $this->testUserPassword,
            'source' => "api.test.test",
        ]);
        $this->assertEquals(($userIdentity->getData())["email"], $this->testUserEmail);
    }


    /* **************************************************************
     * Username/Password login
     */

    public function testUsernamePassword()
    {
        $userIdentity = $this->userAuthenticator->authenticate([
            'username' => $this->testUserEmail,
            'password' => $this->testUserPassword,
        ]);
        $this->assertEquals(($userIdentity->getData())["email"], $this->testUserEmail);
    }

    public function testUsernamePasswordTyzden()
    {
        // backup password
        $pwdBak = $this->user->password;
        $credentials = [
            'username' => $this->testUserEmail,
            'password' => $this->testUserPassword,
        ];

        // check SHA256 hash
        $pwdNew = '{SHA256}' . \hash('sha256', $this->testUserPassword);
        $this->usersRepository->update($this->user, ['password' => $pwdNew]);

        $userIdentity = $this->userAuthenticator->authenticate($credentials);
        $this->assertEquals(($userIdentity->getData())["email"], $this->testUserEmail);

        // check SHA1 hash
        $pwdNew = '{SHA1}' . \hash('sha1', $this->testUserPassword);
        $this->usersRepository->update($this->user, ['password' => $pwdNew]);

        $userIdentity = $this->userAuthenticator->authenticate($credentials);
        $this->assertEquals(($userIdentity->getData())["email"], $this->testUserEmail);

        // check PHPASS hash
        $hasher = new \PHPassLib\Hash\Portable;
        $pwdNew = '{PHPASS}' . $hasher->hash($this->testUserPassword);
        $this->usersRepository->update($this->user, ['password' => $pwdNew]);

        $userIdentity = $this->userAuthenticator->authenticate($credentials);
        $this->assertEquals(($userIdentity->getData())["email"], $this->testUserEmail);

        // revert password change
        $this->usersRepository->update($this->user, ['password' => $pwdBak]);
    }

    public function testIncorrectUsername()
    {
        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage("Nesprávne meno.");
        $this->expectExceptionCode(IAuthenticator::IDENTITY_NOT_FOUND);

        $this->userAuthenticator->authenticate([
            'username' => 'incorrect+' . $this->testUserEmail,
            'password' => $this->testUserPassword,
        ]);
    }

    public function testIncorrectPassword()
    {
        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage("Heslo je nesprávne.");
        $this->expectExceptionCode(IAuthenticator::INVALID_CREDENTIAL);

        $this->userAuthenticator->authenticate([
            'username' => $this->testUserEmail,
            'password' => 'incorrect+'.$this->testUserPassword,
        ]);
    }

    public function testInactiveUser()
    {
        $testInactiveEmail = "inactive@test.test";
        $testInactivePassword = "nbusr123";
        $this->loadUser($testInactiveEmail, $testInactivePassword, UsersRepository::ROLE_USER, false);

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage("Konto je neaktívne.");
        $this->expectExceptionCode(IAuthenticator::IDENTITY_NOT_FOUND);

        $this->userAuthenticator->authenticate([
            'username' => $testInactiveEmail,
            'password' => $testInactivePassword,
        ]);
    }


    /* **************************************************************
     * AutoLoginAuthenticator
     */

    public function testAutoLogin()
    {
        $userIdentity = $this->userAuthenticator->authenticate([
            'user' => $this->user,
            'autoLogin' => true,
        ]);
        $this->assertEquals(($userIdentity->getData())["email"], $this->testUserEmail);
    }

    public function testAutoLoginFalseFlag()
    {
        $this->expectException(AuthenticationException::class);
        $this->expectExceptionCode(IAuthenticator::IDENTITY_NOT_FOUND);

        $userIdentity = $this->userAuthenticator->authenticate([
            'user' => $this->user,
            'autoLogin' => false,
        ]);
    }


    /* **************************************************************
     * MAIL TOKEN
     */

    public function testMailToken()
    {
        $startDate = new DateTime();
        $startDate->setTimestamp(strtotime('-1 hour'));
        $endDate = new DateTime();
        $endDate->setTimestamp(strtotime('+1 hour'));
        $autoLoginToken = $this->autoLogin->addUserToken($this->getUser(), $startDate, $endDate);

        $userIdentity = $this->userAuthenticator->authenticate(['mailToken' => $autoLoginToken->token]);
        $this->assertEquals(($userIdentity->getData())["email"], $this->testUserEmail);
    }

    public function testMailTokenAdmin()
    {
        $startDate = new DateTime();
        $startDate->setTimestamp(strtotime('-1 hour'));
        $endDate = new DateTime();
        $endDate->setTimestamp(strtotime('+1 hour'));
        $autoLoginToken = $this->autoLogin->addUserToken($this->getAdmin(), $startDate, $endDate);

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage("Autologin for this account is disabled");
        $this->expectExceptionCode(IAuthenticator::IDENTITY_NOT_FOUND);

        $this->userAuthenticator->authenticate(['mailToken' => $autoLoginToken->token]);
    }

    public function testMailTokenExpired()
    {
        $startDate = new DateTime();
        $startDate->setTimestamp(strtotime('-2 hour'));
        $endDate = new DateTime();
        $endDate->setTimestamp(strtotime('-1 hour'));
        $autoLoginToken = $this->autoLogin->addUserToken($this->getUser(), $startDate, $endDate);

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage("Token je neplatny");
        $this->expectExceptionCode(IAuthenticator::IDENTITY_NOT_FOUND);

        $this->userAuthenticator->authenticate(['mailToken' => $autoLoginToken->token]);
    }

    public function testMailTokenUsed()
    {
        $startDate = new DateTime();
        $startDate->setTimestamp(strtotime('-1 hour'));
        $endDate = new DateTime();
        $endDate->setTimestamp(strtotime('+1 hour'));
        $autoLoginToken = $this->autoLogin->addUserToken($this->getUser(), $startDate, $endDate, 0);

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage("Token dosiahol maximalny pocet pouziti");
        $this->expectExceptionCode(IAuthenticator::IDENTITY_NOT_FOUND);

        $this->userAuthenticator->authenticate(['mailToken' => $autoLoginToken->token]);
    }

    /* **************************************************************
     * ACCESS TOKEN
     */

    public function testAccessToken()
    {
        $token = $this->accessTokenRepository->add($this->getUser(), $this->accessTokenLastVersion);

        $userIdentity = $this->userAuthenticator->authenticate(['accessToken' => $token->token]);
        $this->assertEquals(($userIdentity->getData())["email"], $this->testUserEmail);
    }

    public function testAccessTokenAdmin()
    {
        $token = $this->accessTokenRepository->add($this->getAdmin(), $this->accessTokenLastVersion);

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage("Automatické prihlásenie pre Vaše konto nie je povolené, prosím prihláste sa znovu");
        $this->expectExceptionCode(IAuthenticator::FAILURE);

        $this->userAuthenticator->authenticate(['accessToken' => $token->token]);
    }

    public function testAccessTokenInvalid()
    {
        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage("Token je neplatný");
        $this->expectExceptionCode(IAuthenticator::FAILURE);

        $this->userAuthenticator->authenticate(['accessToken' => "invalid_token"]);
    }


    /* **************************************************************
     * Helpers
     */

    private function getUser() : IRow
    {
        if (!$this->user) {
            $this->user = $this->loadUser($this->testUserEmail, $this->testUserPassword, UsersRepository::ROLE_USER);
        }

        return $this->user;
    }

    private function getAdmin() : IRow
    {
        if (!$this->admin) {
            $this->admin = $this->loadUser($this->testAdminEmail, $this->testAdminPassword, UsersRepository::ROLE_ADMIN);
        }

        return $this->admin;
    }

    private function loadUser($email, $password, $role = UsersRepository::ROLE_USER, $active = true) : IRow
    {
        $user = $this->userManager->loadUserByEmail($email);
        if (!$user) {
            $user = $this->usersRepository->add($email, $password, '', '', $role, (int)$active);
        }
        return $user;
    }
}
