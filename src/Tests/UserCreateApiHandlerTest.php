<?php

namespace Crm\UsersModule\Tests;

use Crm\ApiModule\Api\JsonResponse;
use Crm\ApiModule\Authorization\NoAuthorization;
use Crm\ApplicationModule\Tests\DatabaseTestCase;
use Crm\UsersModule\Api\UsersCreateHandler;
use Crm\UsersModule\Repositories\DeviceTokensRepository;
use Crm\UsersModule\Repository\AccessTokensRepository;
use Crm\UsersModule\Repository\UserMetaRepository;
use Crm\UsersModule\Repository\UsersRepository;

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

    protected function setUp(): void
    {
        parent::setUp();

        $this->deviceTokensRepository = $this->getRepository(DeviceTokensRepository::class);
        $this->usersRepository = $this->getRepository(UsersRepository::class);
        $this->accessTokensRepository = $this->getRepository(AccessTokensRepository::class);

        $this->handler = $this->inject(UsersCreateHandler::class);
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
        ];
    }

    public function testCreateUserEmailError()
    {
        $this->handler->setAuthorization(new NoAuthorization());
        $response = $this->handler->handle([]); // TODO: fix params

        $this->assertEquals(JsonResponse::class, get_class($response));
        $this->assertEquals(404, $response->getHttpCode());

        $payload = $response->getPayload();
        $this->assertEquals('error', $payload['status']);
    }

    public function testCreateUserOnlyWithEmail()
    {
        $_POST['email'] = '0test@user.site';

        $this->handler->setAuthorization(new NoAuthorization());
        $response = $this->handler->handle([]); // TODO: fix params

        $this->assertEquals(JsonResponse::class, get_class($response));
        $this->assertEquals($response->getHttpCode(), 200);

        $payload = $response->getPayload();
        $this->assertEquals('ok', $payload['status']);
        $this->assertArrayHasKey('user', $payload);

        $user = $this->usersRepository->find($payload['user']['id']);
        $this->assertNotEmpty($user);

        unset($_POST['email']);
    }

    public function testCreateUserPairsDeviceAndAccessToken()
    {
        $deviceToken = $this->deviceTokensRepository->generate('testdevid123');

        $_POST['email'] = '0test2@user.site';
        $_POST['device_token'] = $deviceToken->token;

        $this->handler->setAuthorization(new NoAuthorization());
        $response = $this->handler->handle([]); // TODO: fix params

        $this->assertEquals(JsonResponse::class, get_class($response));
        $this->assertEquals($response->getHttpCode(), 200);

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

        unset($_POST['email'], $_POST['device_token']);
    }

    public function testCreateUserNotExistingDeviceToken()
    {
        $_POST['email'] = '0test2@user.site';
        $_POST['device_token'] = 'devtok_sd8a907sas987du';

        $this->handler->setAuthorization(new NoAuthorization());
        $response = $this->handler->handle([]); // TODO: fix params

        $this->assertEquals(JsonResponse::class, get_class($response));
        $this->assertEquals($response->getHttpCode(), 400);

        $payload = $response->getPayload();
        $this->assertEquals('error', $payload['status']);
        $this->assertEquals('device_token_doesnt_exist', $payload['code']);

        unset($_POST['email'], $_POST['device_token']);
    }
}
