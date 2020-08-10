<?php

namespace Crm\UsersModule\Tests;

use Crm\ApiModule\Api\JsonResponse;
use Crm\ApiModule\Authorization\NoAuthorization;
use Crm\ApplicationModule\Tests\DatabaseTestCase;
use Crm\UsersModule\Api\UsersConfirmApiHandler;
use Crm\UsersModule\Repository\UsersRepository;
use Crm\UsersModule\Seeders\UsersSeeder;
use Nette\Http\Response;

class UsersConfirmApiHandlerTest extends DatabaseTestCase
{
    /** @var UsersConfirmApiHandler */
    private $handler;

    protected function requiredRepositories(): array
    {
        return [
            UsersRepository::class
        ];
    }

    protected function requiredSeeders(): array
    {
        return [
            UsersSeeder::class
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->handler = $this->inject(UsersConfirmApiHandler::class);
    }

    public function testConfirmUserNoEmail()
    {
        $response = $this->handler->handle(new NoAuthorization());

        $this->assertEquals(JsonResponse::class, get_class($response));
        $this->assertEquals(Response::S400_BAD_REQUEST, $response->getHttpCode());

        $payload = $response->getPayload();
        $this->assertEquals('error', $payload['status']);
    }

    public function testConfirmUserUserNotFound()
    {
        $_POST['email'] = '0test@user.site';

        $response = $this->handler->handle(new NoAuthorization());

        $this->assertEquals(JsonResponse::class, get_class($response));
        $this->assertEquals(Response::S404_NOT_FOUND, $response->getHttpCode());

        $payload = $response->getPayload();
        $this->assertEquals('error', $payload['status']);
        $this->assertEquals('user_not_found', $payload['code']);
    }

    public function testConfirmUserUserFound()
    {
        $_POST['email'] = 'admin@admin.sk';

        $response = $this->handler->handle(new NoAuthorization());

        $this->assertEquals(JsonResponse::class, get_class($response));
        $this->assertEquals(Response::S200_OK, $response->getHttpCode());

        $payload = $response->getPayload();
        $this->assertEquals('ok', $payload['status']);
    }
}
