<?php

namespace Crm\UsersModule\Tests;

use Crm\ApiModule\Api\JsonResponse;
use Crm\ApiModule\Authorization\NoAuthorization;
use Crm\ApplicationModule\Tests\DatabaseTestCase;
use Crm\UsersModule\Api\GetDeviceTokenApiHandler;
use Crm\UsersModule\Repositories\DeviceTokensRepository;
use Crm\UsersModule\Repository\AccessTokensRepository;
use Crm\UsersModule\Repository\UserMetaRepository;
use Crm\UsersModule\Repository\UsersRepository;
use Crm\UsersModule\Seeders\UsersSeeder;
use Nette\Http\Response;

class GetDeviceTokenApiHandlerTest extends DatabaseTestCase
{
    private $accessTokensRepository;

    private $deviceTokensRepository;

    private $usersRepository;

    private $handler;

    protected function requiredSeeders(): array
    {
        return [
            UsersSeeder::class
        ];
    }

    protected function requiredRepositories(): array
    {
        return [
            AccessTokensRepository::class,
            DeviceTokensRepository::class,
            UsersRepository::class,
            UserMetaRepository::class,
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->accessTokensRepository = $this->getRepository(AccessTokensRepository::class);
        $this->deviceTokensRepository = $this->getRepository(DeviceTokensRepository::class);
        $this->usersRepository = $this->getRepository(UsersRepository::class);
        $this->handler = $this->inject(GetDeviceTokenApiHandler::class);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        unset($_POST);
    }

    public function testGenerateDeviceToken()
    {
        $_POST['device_id'] = 'asd123';

        $response = $this->handler->handle(new NoAuthorization());
        $this->assertEquals(JsonResponse::class, get_class($response));

        $payload = $response->getPayload();
        $this->assertArrayHasKey('device_token', $payload);
    }

    public function testMissingDeviceIdParam()
    {
        $response = $this->handler->handle(new NoAuthorization());
        $this->assertEquals(JsonResponse::class, get_class($response));

        $payload = $response->getPayload();
        $this->assertArrayHasKey('status', $payload);
        $this->assertEquals('error', $payload['status']);
    }

    public function testWrongAccessToken()
    {
        $_POST['device_id'] = 'asd123';
        $_POST['access_token'] = '1478';

        $response = $this->handler->handle(new NoAuthorization());

        $this->assertEquals(JsonResponse::class, get_class($response));
        $this->assertEquals(Response::S400_BAD_REQUEST, $response->getHttpCode());

        $payload = $response->getPayload();
        $this->assertEquals('error', $payload['status']);
        $this->assertEquals('Access token not valid', $payload['message']);
    }

    public function testGenerateWithPairAccessToken()
    {
        $_POST['device_id'] = 'asd123';

        $user = $this->usersRepository->getByEmail('admin@admin.sk');
        $accessTokenRow = $this->accessTokensRepository->add($user, 1);
        $_POST['access_token'] = $accessTokenRow->token;

        $response = $this->handler->handle(new NoAuthorization());

        $this->assertEquals(JsonResponse::class, get_class($response));
        $this->assertEquals(Response::S200_OK, $response->getHttpCode());

        $payload = $response->getPayload();
        $this->assertArrayHasKey('device_token', $payload);

        $accessToken = $this->accessTokensRepository->loadToken($accessTokenRow->token);
        $deviceToken = $this->deviceTokensRepository->findByToken($payload['device_token']);
        $this->assertEquals($accessToken->device_token_id, $deviceToken->id);
    }
}
