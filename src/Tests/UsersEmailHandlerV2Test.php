<?php

namespace Crm\UsersModule\Tests;

use Crm\ApiModule\Authorization\NoAuthorization;
use Crm\ApplicationModule\Authenticator\AuthenticatorManagerInterface;
use Crm\ApplicationModule\Tests\DatabaseTestCase;
use Crm\UsersModule\Api\v2\UsersEmailHandler;
use Crm\UsersModule\Auth\UserManager;
use Crm\UsersModule\Authenticator\UsersAuthenticator;
use Crm\UsersModule\Events\LoginAttemptEvent;
use Crm\UsersModule\Events\LoginAttemptHandler;
use Crm\UsersModule\Repository\LoginAttemptsRepository;
use Crm\UsersModule\Repository\UserMetaRepository;
use Crm\UsersModule\Repository\UsersRepository;
use Crm\UsersModule\Seeders\UsersSeeder;
use Crm\UsersModule\User\UnclaimedUser;
use League\Event\Emitter;
use Nette\Http\IResponse;
use Nette\Utils\Random;
use Tomaj\NetteApi\Response\JsonApiResponse;

class UsersEmailHandlerV2Test extends DatabaseTestCase
{
    /** @var UsersEmailHandler */
    private $handler;

    /** @var AuthenticatorManagerInterface */
    private $authenticatorManager;

    /** @var LoginAttemptsRepository */
    private $loginAttemptsRepository;

    /** @var UserManager */
    private $userManager;

    /** @var UnclaimedUser */
    private $unclaimedUser;

    private $emitter;

    protected function requiredSeeders(): array
    {
        return [
            UsersSeeder::class
        ];
    }

    protected function requiredRepositories(): array
    {
        return [
            LoginAttemptsRepository::class,
            UsersRepository::class,
            UserMetaRepository::class
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->handler = $this->inject(UsersEmailHandler::class);
        $this->userManager = $this->inject(UserManager::class);
        $this->unclaimedUser = $this->inject(UnclaimedUser::class);
        $this->loginAttemptsRepository = $this->getRepository(LoginAttemptsRepository::class);

        $this->emitter = $this->inject(Emitter::class);
        $this->emitter->addListener(
            LoginAttemptEvent::class,
            $this->inject(LoginAttemptHandler::class)
        );

        $this->authenticatorManager = $this->inject(AuthenticatorManagerInterface::class);
        $this->authenticatorManager->registerAuthenticator($this->inject(UsersAuthenticator::class));
    }

    protected function tearDown(): void
    {
        $this->emitter->removeListener(
            LoginAttemptEvent::class,
            $this->inject(LoginAttemptHandler::class)
        );

        parent::tearDown();
    }

    public function testNoEmail()
    {
        // TODO: Fix tests of missing required parameters (remp/crm#2319)
        $this->markTestSkipped('Skipped until remp/crm#2319 is resolved');
        $this->handler->setAuthorization(new NoAuthorization());
        $response = $this->handler->handle([]); // TODO: fix params

        $this->assertEquals(JsonApiResponse::class, get_class($response));
        $this->assertEquals(IResponse::S200_OK, $response->getHttpCode());

        $payload = $response->getPayload();
        $this->assertEquals('error', $payload['status']);
        $this->assertEquals('email_missing', $payload['code']);
    }

    public function testInvalidEmail()
    {
        $this->handler->setAuthorization(new NoAuthorization());
        $response = $this->handler->handle([
            'email' =>'0test@user',
        ]);

        $this->assertEquals(JsonApiResponse::class, get_class($response));
        $this->assertEquals(IResponse::S422_UNPROCESSABLE_ENTITY, $response->getCode());

        $payload = $response->getPayload();
        $this->assertEquals('error', $payload['status']);
        $this->assertEquals('invalid_email', $payload['code']);
    }

    public function testValidEmailNoUser()
    {
        $email = 'example@example.com';

        $this->handler->setAuthorization(new NoAuthorization());
        $response = $this->handler->handle([
            'email' => $email,
        ]);
        $lastAttempt = $this->lastLoginAttempt();

        $this->assertEquals(JsonApiResponse::class, get_class($response));
        $this->assertEquals(IResponse::S200_OK, $response->getCode());

        $payload = $response->getPayload();
        $this->assertEquals('available', $payload['status']);
        $this->assertEquals($email, $payload['email']);
        $this->assertEquals(null, $payload['id']);
        $this->assertEquals(null, $payload['password']);
        $this->assertEquals(LoginAttemptsRepository::STATUS_NOT_FOUND_EMAIL, $lastAttempt->status);
    }

    public function testClaimedUserNoPassword()
    {
        $email = 'user@user.sk';

        $this->handler->setAuthorization(new NoAuthorization());
        $response = $this->handler->handle([
            'email' => $email,
        ]);
        $lastAttempt = $this->lastLoginAttempt();

        $this->assertEquals(JsonApiResponse::class, get_class($response));
        $this->assertEquals(IResponse::S200_OK, $response->getCode());

        $payload = $response->getPayload();
        $user = $this->userManager->loadUserByEmail($email);

        $this->assertEquals('taken', $payload['status']);
        $this->assertEquals($email, $payload['email']);
        $this->assertEquals($user->id, $payload['id']);
        $this->assertEquals(null, $payload['password']);
        $this->assertEquals(LoginAttemptsRepository::STATUS_WRONG_PASS, $lastAttempt->status);
    }

    public function testClaimedUserInvalidPassword()
    {
        $email = 'user@user.sk';

        $this->handler->setAuthorization(new NoAuthorization());
        $response = $this->handler->handle([
            'email' => $email,
            'password' => 'invalid',
        ]);
        $lastAttempt = $this->lastLoginAttempt();

        $this->assertEquals(JsonApiResponse::class, get_class($response));
        $this->assertEquals(IResponse::S401_UNAUTHORIZED, $response->getCode());

        $payload = $response->getPayload();
        $user = $this->userManager->loadUserByEmail($email);

        $this->assertEquals('taken', $payload['status']);
        $this->assertEquals($email, $payload['email']);
        $this->assertEquals($user->id, $payload['id']);
        $this->assertEquals(false, $payload['password']);
        $this->assertEquals(LoginAttemptsRepository::STATUS_WRONG_PASS, $lastAttempt->status);
    }

    public function testClaimedUserCorrectPassword()
    {
        $email = 'user@user.sk';

        $this->handler->setAuthorization(new NoAuthorization());
        $response = $this->handler->handle([
            'email' => $email,
            'password' => 'password',
        ]);
        $lastAttempt = $this->lastLoginAttempt();

        $this->assertEquals(JsonApiResponse::class, get_class($response));
        $this->assertEquals(IResponse::S200_OK, $response->getCode());

        $payload = $response->getPayload();
        $user = $this->userManager->loadUserByEmail($email);

        $this->assertEquals('taken', $payload['status']);
        $this->assertEquals($email, $payload['email']);
        $this->assertEquals($user->id, $payload['id']);
        $this->assertEquals(true, $payload['password']);
        $this->assertEquals(LoginAttemptsRepository::STATUS_OK, $lastAttempt->status);
    }

    public function testUnclaimedUser()
    {
        $email = 'unclaimed@unclaimed.sk';
        $this->unclaimedUser->createUnclaimedUser($email);

        $this->handler->setAuthorization(new NoAuthorization());
        $response = $this->handler->handle([
            'email' => $email,
        ]);
        $lastAttempt = $this->lastLoginAttempt();

        $this->assertEquals(JsonApiResponse::class, get_class($response));
        $this->assertEquals(IResponse::S200_OK, $response->getCode());

        $payload = $response->getPayload();
        $user = $this->userManager->loadUserByEmail($email);

        $this->assertEquals('available', $payload['status']);
        $this->assertEquals($email, $payload['email']);
        $this->assertNull($payload['id']);
        $this->assertNull($payload['password']);
        $this->assertEquals(LoginAttemptsRepository::STATUS_UNCLAIMED_USER, $lastAttempt->status);
    }

    public function testEmailTooLong()
    {
        $email = Random::generate('255') . '@example.com';

        $this->handler->setAuthorization(new NoAuthorization());
        $response = $this->handler->handle([
            'email' => $email,
        ]);

        $this->assertEquals(JsonApiResponse::class, get_class($response));
        $this->assertEquals(IResponse::S422_UNPROCESSABLE_ENTITY, $response->getCode());
    }


    private function lastLoginAttempt()
    {
        return $this->loginAttemptsRepository->getTable()
            ->order('created_at DESC')
            ->limit(1)
            ->fetch();
    }
}
