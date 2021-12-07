<?php

namespace Crm\UsersModule\Tests;

use Crm\ApplicationModule\Authenticator\AuthenticatorManagerInterface;
use Crm\ApplicationModule\Tests\DatabaseTestCase;
use Crm\UsersModule\Auth\Access\AccessToken;
use Crm\UsersModule\Auth\AutoLogin\AutoLogin;
use Crm\UsersModule\Auth\UserAuthenticator;
use Crm\UsersModule\Auth\UserManager;
use Crm\UsersModule\Repository\AccessTokensRepository;
use Crm\UsersModule\Repository\LoginAttemptsRepository;
use Crm\UsersModule\Repository\UserMetaRepository;
use Crm\UsersModule\Repository\UsersRepository;
use Crm\UsersModule\User\UnclaimedUser;
use Nette\Database\Table\IRow;
use Nette\Security\AuthenticationException;
use Nette\Security\IAuthenticator;

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

    /** @var UnclaimedUser $unclaimedUser */
    private $unclaimedUser;

    private $testUserEmail = "test@test.test";
    private $testUserPassword = "nbusr123";
    private $testAdminEmail = "admin@test.test";
    private $testAdminPassword = "nbusr123";

    public function requiredRepositories(): array
    {
        return [
            UsersRepository::class,
            UserMetaRepository::class,
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

        $authenticatorManager = $this->inject(AuthenticatorManagerInterface::class);
        $authenticatorManager->registerAuthenticator(
            $this->inject(\Crm\UsersModule\Authenticator\AutoLoginAuthenticator::class),
            700
        );
        $authenticatorManager->registerAuthenticator(
            $this->inject(\Crm\UsersModule\Authenticator\UsersAuthenticator::class),
            500
        );
        $authenticatorManager->registerAuthenticator(
            $this->inject(\Crm\UsersModule\Authenticator\AccessTokenAuthenticator::class),
            200
        );
        $authenticatorManager->registerAuthenticator(
            $this->inject(\Crm\UsersModule\Authenticator\AutoLoginTokenAuthenticator::class),
            800
        );

        $this->userAuthenticator = $this->inject(UserAuthenticator::class);
        $this->userManager = $this->inject(UserManager::class);
        $this->autoLogin = $this->inject(AutoLogin::class);
        $this->unclaimedUser = $this->inject(UnclaimedUser::class);

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

    public function testUnclaimedUser()
    {
        $testUnclaimedEmail = "unclaimed@test.test";
        $testUnclaimedPassword = "nbusr123";
        $this->unclaimedUser->createUnclaimedUser($testUnclaimedEmail);

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage("Konto je predregistrované.");
        $this->expectExceptionCode(IAuthenticator::NOT_APPROVED);

        $this->userAuthenticator->authenticate([
            'username' => $testUnclaimedEmail,
            'password' => $testUnclaimedPassword,
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
