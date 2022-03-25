<?php

namespace Crm\UsersModule\Tests;

use Crm\ApiModule\Authorization\NoAuthorization;
use Crm\ApplicationModule\Authenticator\AuthenticatorManagerInterface;
use Crm\ApplicationModule\Tests\DatabaseTestCase;
use Crm\UsersModule\Api\UsersCreateHandler;
use Crm\UsersModule\Api\UsersLoginHandler;
use Crm\UsersModule\Authenticator\UsersAuthenticator;
use Crm\UsersModule\Events\SignEventHandler;
use Crm\UsersModule\Events\UserSignInEvent;
use Crm\UsersModule\Repositories\DeviceTokensRepository;
use Crm\UsersModule\Repository\AccessTokensRepository;
use Crm\UsersModule\Repository\UserMetaRepository;
use Crm\UsersModule\Repository\UsersRepository;
use Crm\UsersModule\User\UnclaimedUser;
use League\Event\Emitter;
use Tomaj\NetteApi\Response\JsonApiResponse;

class UserLoginApiHandlerTest extends DatabaseTestCase
{
    const LOGIN = '1test@user.st';
    const PASSWORD = 'password';

    /** @var DeviceTokensRepository */
    private $deviceTokensRepository;

    /** @var UsersRepository */
    private $usersRepository;

    /** @var UsersCreateHandler */
    private $handler;

    /** @var AccessTokensRepository */
    private $accessTokensRepository;

    /** @var AuthenticatorManagerInterface */
    private $authenticatorManager;

    /** @var UnclaimedUser */
    private $unclaimedUser;

    private $emitter;

    private $user;

    protected function requiredSeeders(): array
    {
        return [];
    }

    protected function requiredRepositories(): array
    {
        return [
            DeviceTokensRepository::class,
            UsersRepository::class,
            UserMetaRepository::class,
            AccessTokensRepository::class,
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->deviceTokensRepository = $this->getRepository(DeviceTokensRepository::class);
        $this->usersRepository = $this->getRepository(UsersRepository::class);
        $this->accessTokensRepository = $this->getRepository(AccessTokensRepository::class);

        $this->unclaimedUser = $this->inject(UnclaimedUser::class);
        $this->handler = $this->inject(UsersLoginHandler::class);

        $this->emitter = $this->inject(Emitter::class);
        $this->emitter->addListener(
            UserSignInEvent::class,
            $this->inject(SignEventHandler::class)
        );

        $this->authenticatorManager = $this->inject(AuthenticatorManagerInterface::class);
        $this->authenticatorManager->registerAuthenticator($this->inject(UsersAuthenticator::class));
    }

    protected function tearDown(): void
    {
        $this->emitter->removeListener(
            UserSignInEvent::class,
            $this->inject(SignEventHandler::class)
        );

        parent::tearDown();
    }

    public function testNotExistingUser()
    {
        $this->handler->setAuthorization(new NoAuthorization());
        $response = $this->handler->handle([]); // TODO: fix params

        $this->assertEquals(JsonApiResponse::class, get_class($response));
        $this->assertEquals(400, $response->getCode());

        $payload = $response->getPayload();
        $this->assertEquals('error', $payload['status']);
        $this->assertEquals('no_email', $payload['error']);
    }

    public function testUnclaimedUser()
    {
        $this->unclaimedUser->createUnclaimedUser(self::LOGIN);

        $_POST['email'] = self::LOGIN;
        $_POST['password'] = self::PASSWORD;

        $this->handler->setAuthorization(new NoAuthorization());
        $response = $this->handler->handle([]); // TODO: fix params

        $this->assertEquals(JsonApiResponse::class, get_class($response));
        $this->assertEquals(400, $response->getCode());

        $payload = $response->getPayload();
        $this->assertEquals('error', $payload['status']);
        $this->assertEquals('auth_failed', $payload['error']);
    }

    public function testLoginUser()
    {
        $user = $this->getUser();

        $_POST['email'] = self::LOGIN;
        $_POST['password'] = self::PASSWORD;

        $this->handler->setAuthorization(new NoAuthorization());
        $response = $this->handler->handle([]); // TODO: fix params

        $this->assertEquals(JsonApiResponse::class, get_class($response));
        $this->assertEquals(200, $response->getCode());

        $payload = $response->getPayload();
        $this->assertArrayHasKey('user', $payload);

        unset($_POST['email'], $_POST['password']);
    }

    public function testPairAccessAndDeviceTokens()
    {
        $this->getUser();

        $deviceToken = $this->deviceTokensRepository->generate('poiqwe123');

        $_POST['email'] = self::LOGIN;
        $_POST['password'] = self::PASSWORD;
        $_POST['device_token'] = $deviceToken->token;

        $this->handler->setAuthorization(new NoAuthorization());
        $response = $this->handler->handle([]); // TODO: fix params

        $this->assertEquals(JsonApiResponse::class, get_class($response));
        $this->assertEquals(200, $response->getCode());

        $payload = $response->getPayload();
        $this->assertArrayHasKey('access', $payload);
        $this->assertArrayHasKey('token', $payload['access']);

        $accessToken = $this->accessTokensRepository->loadToken($payload['access']['token']);

        $pair = $this->accessTokensRepository->getTable()
            ->where('id', $accessToken->id)
            ->where('device_token_id', $deviceToken->id)
            ->fetch();

        $this->assertNotEmpty($pair);

        unset($_POST['email'], $_POST['password'], $_POST['device_token']);
    }

    private function getUser()
    {
        if (!$this->user) {
            $this->user = $this->usersRepository->add(self::LOGIN, self::PASSWORD);
        }
        return $this->user;
    }
}
