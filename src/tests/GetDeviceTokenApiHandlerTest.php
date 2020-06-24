<?php

namespace Crm\UsersModule\Tests;

use Crm\ApiModule\Api\JsonResponse;
use Crm\ApiModule\Authorization\NoAuthorization;
use Crm\ApplicationModule\Tests\DatabaseTestCase;
use Crm\UsersModule\Api\GetDeviceTokenApiHandler;
use Crm\UsersModule\Repositories\DeviceTokensRepository;

class GetDeviceTokenApiHandlerTest extends DatabaseTestCase
{
    private $deviceTokensRepository;

    private $handler;

    protected function requiredRepositories(): array
    {
        return [
            DeviceTokensRepository::class
        ];
    }

    protected function requiredSeeders(): array
    {
        return [];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->deviceTokensRepository = $this->inject(DeviceTokensRepository::class);
        $this->handler = $this->inject(GetDeviceTokenApiHandler::class);
    }

    public function testGenerateDeviceToken()
    {
        $_POST['device_id'] = 'asd123';

        $response = $this->handler->handle(new NoAuthorization());
        $this->assertEquals(JsonResponse::class, get_class($response));

        $payload = $response->getPayload();
        $this->assertArrayHasKey('device_token', $payload);

        unset($_POST['device_id']);
    }

    public function testMissingDeviceIdParam()
    {
        $response = $this->handler->handle(new NoAuthorization());
        $this->assertEquals(JsonResponse::class, get_class($response));

        $payload = $response->getPayload();
        $this->assertArrayHasKey('status', $payload);
        $this->assertEquals('error', $payload['status']);
    }
}
