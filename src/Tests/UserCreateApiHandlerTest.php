<?php

namespace Crm\UsersModule\Tests;

use Crm\ApiModule\Api\JsonResponse;
use Crm\ApiModule\Authorization\NoAuthorization;
use Crm\ApplicationModule\Tests\DatabaseTestCase;
use Crm\UsersModule\Api\UsersCreateHandler;
use Crm\UsersModule\Events\NewUserEvent;
use Crm\UsersModule\Events\UserRegisteredEvent;
use Crm\UsersModule\Repositories\DeviceTokensRepository;
use Crm\UsersModule\Repository\AccessTokensRepository;
use Crm\UsersModule\Repository\RegistrationAttemptsRepository;
use Crm\UsersModule\Repository\UserMetaRepository;
use Crm\UsersModule\Repository\UsersRepository;
use Crm\UsersModule\User\UnclaimedUser;
use League\Event\AbstractListener;
use League\Event\Emitter;
use Nette\Http\Response;

class UserCreateApiHandlerTest extends DatabaseTestCase
{
    /** @var DeviceTokensRepository */
    private $deviceTokensRepository;

    /** @var UsersRepository */
    private $usersRepository;

    /** @var UsersCreateHandler */
    private $handler;

    /** @var AccessTokensRepository */
    private $accessTokensRepository;

    /** @var UnclaimedUser */
    private $unclaimedUser;

    /** @var Emitter */
    private $emitter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->deviceTokensRepository = $this->getRepository(DeviceTokensRepository::class);
        $this->usersRepository = $this->getRepository(UsersRepository::class);
        $this->accessTokensRepository = $this->getRepository(AccessTokensRepository::class);
        $this->unclaimedUser = $this->inject(UnclaimedUser::class);
        $this->emitter = $this->inject(Emitter::class);

        $this->handler = $this->inject(UsersCreateHandler::class);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        \Mockery::close();
    }

    protected function requiredSeeders(): array
    {
        return [];
    }

    protected function requiredRepositories(): array
    {
        return [
            DeviceTokensRepository::class,
            UsersRepository::class,
            AccessTokensRepository::class,
            UserMetaRepository::class,
            RegistrationAttemptsRepository::class,
        ];
    }

    public function testCreateUserEmailError()
    {
        // TODO: Fix tests of missing required parameters (remp/crm#2319)
        $this->markTestSkipped('Skipped until remp/crm#2319 is resolved');
        $this->handler->setAuthorization(new NoAuthorization());
        $response = $this->handler->handle([]); // TODO: fix params

        $this->assertEquals(JsonResponse::class, get_class($response));
        $this->assertEquals(404, $response->getHttpCode());

        $payload = $response->getPayload();
        $this->assertEquals('error', $payload['status']);
    }

    public function testCreateUserOnlyWithEmail()
    {
        /** @var AbstractListener $listenerNewUser */
        $listenerNewUser = \Mockery::mock(AbstractListener::class)->shouldReceive('handle')->once()->getMock();
        $this->emitter->addListener(NewUserEvent::class, $listenerNewUser);
        /** @var AbstractListener $listenerUserRegistered */
        $listenerUserRegistered = \Mockery::mock(AbstractListener::class)->shouldReceive('handle')->once()->getMock();
        $this->emitter->addListener(UserRegisteredEvent::class, $listenerUserRegistered);

        $this->handler->setAuthorization(new NoAuthorization());
        $response = $this->handler->handle([
            'email' => '0test@user.site',
        ]);

        $this->assertEquals(JsonResponse::class, get_class($response));
        $this->assertEquals(Response::S200_OK, $response->getHttpCode());

        $payload = $response->getPayload();
        $this->assertEquals('ok', $payload['status']);
        $this->assertArrayHasKey('user', $payload);

        $user = $this->usersRepository->find($payload['user']['id']);
        $this->assertNotEmpty($user);

        $this->assertFalse($this->unclaimedUser->isUnclaimedUser($user));
    }

    public function testCreateUserPairsDeviceAndAccessToken()
    {
        $deviceToken = $this->deviceTokensRepository->generate('testdevid123');

        /** @var AbstractListener $listenerNewUser */
        $listenerNewUser = \Mockery::mock(AbstractListener::class)->shouldReceive('handle')->once()->getMock();
        $this->emitter->addListener(NewUserEvent::class, $listenerNewUser);
        /** @var AbstractListener $listenerUserRegistered */
        $listenerUserRegistered = \Mockery::mock(AbstractListener::class)->shouldReceive('handle')->once()->getMock();
        $this->emitter->addListener(UserRegisteredEvent::class, $listenerUserRegistered);

        $this->handler->setAuthorization(new NoAuthorization());
        $response = $this->handler->handle([
            'email' => '0test2@user.site',
            'device_token' => $deviceToken->token,
        ]);

        $this->assertEquals(JsonResponse::class, get_class($response));
        $this->assertEquals(Response::S200_OK, $response->getHttpCode());

        $payload = $response->getPayload();
        $this->assertEquals('ok', $payload['status']);
        $this->assertArrayHasKey('user', $payload);

        $user = $this->usersRepository->find($payload['user']['id']);
        $this->assertNotEmpty($user);

        $accessToken = $this->accessTokensRepository->loadToken($payload['access']['token']);
        $pair = $this->accessTokensRepository->getTable()
            ->where('id', $accessToken->id)
            ->where('device_token_id', $deviceToken->id)
            ->fetch();

        $this->assertNotEmpty($pair);
        $this->assertFalse($this->unclaimedUser->isUnclaimedUser($user));
    }

    public function testCreateUserNotExistingDeviceToken()
    {
        $this->handler->setAuthorization(new NoAuthorization());
        $response = $this->handler->handle([
            'email' => '0test2@user.site',
            'device_token' => 'devtok_sd8a907sas987du',
        ]);

        $this->assertEquals(JsonResponse::class, get_class($response));
        $this->assertEquals(Response::S400_BAD_REQUEST, $response->getHttpCode());

        $payload = $response->getPayload();
        $this->assertEquals('error', $payload['status']);
        $this->assertEquals('device_token_doesnt_exist', $payload['code']);
    }

    public function testCreateUnclaimedUser()
    {
        /** @var AbstractListener $listenerNewUser */
        $listenerNewUser = \Mockery::mock(AbstractListener::class)->shouldReceive('handle')->once()->getMock();
        $this->emitter->addListener(NewUserEvent::class, $listenerNewUser);
        /** @var AbstractListener $listenerUserRegistered */
        $listenerUserRegistered = \Mockery::mock(AbstractListener::class)->shouldReceive('handle')->never()->getMock();
        $this->emitter->addListener(UserRegisteredEvent::class, $listenerUserRegistered);

        $this->handler->setAuthorization(new NoAuthorization());
        $response = $this->handler->handle([
            'email' => '0test2@user.site',
            'unclaimed' => true,
        ]);

        $this->assertEquals(JsonResponse::class, get_class($response));
        $this->assertEquals(Response::S200_OK, $response->getHttpCode());

        $payload = $response->getPayload();
        $this->assertEquals('ok', $payload['status']);
        $this->assertArrayHasKey('user', $payload);

        $user = $this->usersRepository->find($payload['user']['id']);
        $this->assertNotEmpty($user);

        $this->assertTrue($this->unclaimedUser->isUnclaimedUser($user));
    }

    public function testUnclaimedUserAlreadyExists()
    {
        $email = '0test2@user.site';
        $this->unclaimedUser->createUnclaimedUser($email);

        $this->handler->setAuthorization(new NoAuthorization());
        $response = $this->handler->handle([
            'email' => $email,
            'unclaimed' => true,
        ]);

        $this->assertEquals(JsonResponse::class, get_class($response));
        $this->assertEquals(Response::S404_NOT_FOUND, $response->getHttpCode());

        $payload = $response->getPayload();
        $this->assertEquals('error', $payload['status']);
        $this->assertEquals('email_taken', $payload['code']);
    }

    public function testStandardUserAlreadyExists()
    {
        $email = '0test2@user.site';
        $this->usersRepository->add($email, '123456');

        $this->handler->setAuthorization(new NoAuthorization());
        $response = $this->handler->handle([
            'email' => $email,
        ]);

        $this->assertEquals(JsonResponse::class, get_class($response));
        $this->assertEquals(Response::S404_NOT_FOUND, $response->getHttpCode());

        $payload = $response->getPayload();
        $this->assertEquals('error', $payload['status']);
        $this->assertEquals('email_taken', $payload['code']);
    }

    public function testRegisterUnclaimedUser()
    {
        $email = '0test2@user.site';
        $this->unclaimedUser->createUnclaimedUser($email);

        /** @var AbstractListener $listenerNewUser */
        $listenerNewUser = \Mockery::mock(AbstractListener::class)->shouldReceive('handle')->once()->getMock();
        $this->emitter->addListener(NewUserEvent::class, $listenerNewUser);
        /** @var AbstractListener $listenerUserRegistered */
        $listenerUserRegistered = \Mockery::mock(AbstractListener::class)->shouldReceive('handle')->once()->getMock();
        $this->emitter->addListener(UserRegisteredEvent::class, $listenerUserRegistered);

        $this->handler->setAuthorization(new NoAuthorization());
        $response = $this->handler->handle([
            'email' => $email,
        ]);

        $this->assertEquals(JsonResponse::class, get_class($response));
        $this->assertEquals(Response::S200_OK, $response->getHttpCode());

        $payload = $response->getPayload();
        $this->assertEquals('ok', $payload['status']);
        $this->assertArrayHasKey('user', $payload);

        $user = $this->usersRepository->find($payload['user']['id']);
        $this->assertNotEmpty($user);

        $this->assertFalse($this->unclaimedUser->isUnclaimedUser($user));
    }
}
