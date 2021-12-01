<?php

namespace Crm\UsersModule\Tests;

use Crm\ApiModule\Api\JsonResponse;
use Crm\ApiModule\Authorization\NoAuthorization;
use Crm\ApplicationModule\Tests\DatabaseTestCase;
use Crm\UsersModule\Api\UsersEmailHandler;
use Crm\UsersModule\Auth\UserManager;
use Crm\UsersModule\Repository\UserMetaRepository;
use Crm\UsersModule\Repository\UsersRepository;
use Crm\UsersModule\Seeders\UsersSeeder;
use Crm\UsersModule\User\UnclaimedUser;
use Nette\Http\IResponse;

class UsersEmailHandlerTest extends DatabaseTestCase
{
    /** @var UsersEmailHandler */
    private $handler;

    /** @var UserManager */
    private $userManager;

    /** @var UnclaimedUser */
    private $unclaimedUser;

    protected function requiredSeeders(): array
    {
        return [
            UsersSeeder::class
        ];
    }

    protected function requiredRepositories(): array
    {
        return [
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
    }

    public function testNoEmail()
    {
        $response = $this->handler->handle(new NoAuthorization());

        $this->assertEquals(JsonResponse::class, get_class($response));
        $this->assertEquals(IResponse::S200_OK, $response->getHttpCode());

        $payload = $response->getPayload();
        $this->assertEquals('error', $payload['status']);
        $this->assertEquals('email_missing', $payload['code']);
    }

    public function testInvalidEmail()
    {
         $_POST['email'] = '0test@user';

        $response = $this->handler->handle(new NoAuthorization());

        $this->assertEquals(JsonResponse::class, get_class($response));
        $this->assertEquals(IResponse::S200_OK, $response->getHttpCode());

        $payload = $response->getPayload();
        $this->assertEquals('error', $payload['status']);
        $this->assertEquals('invalid_email', $payload['code']);
    }

    public function testValidEmailNoUser()
    {
        $email = 'example@example.com';
        $_POST['email'] = $email;

        $response = $this->handler->handle(new NoAuthorization());

        $this->assertEquals(JsonResponse::class, get_class($response));
        $this->assertEquals(IResponse::S200_OK, $response->getHttpCode());

        $payload = $response->getPayload();
        $this->assertEquals('available', $payload['status']);
        $this->assertEquals($email, $payload['email']);
        $this->assertEquals(null, $payload['id']);
        $this->assertEquals(null, $payload['password']);
    }

    public function testClaimedUserNoPassword()
    {
        $email = 'user@user.sk';
        $_POST['email'] = $email;

        $response = $this->handler->handle(new NoAuthorization());

        $this->assertEquals(JsonResponse::class, get_class($response));
        $this->assertEquals(IResponse::S200_OK, $response->getHttpCode());

        $payload = $response->getPayload();
        $user = $this->userManager->loadUserByEmail($email);

        $this->assertEquals('taken', $payload['status']);
        $this->assertEquals($email, $payload['email']);
        $this->assertEquals($user->id, $payload['id']);
        $this->assertEquals(null, $payload['password']);
    }

    public function testClaimedUserInvalidPassword()
    {
        $email = 'user@user.sk';
        $_POST['email'] = $email;
        $_POST['password'] = 'invalid';

        $response = $this->handler->handle(new NoAuthorization());

        $this->assertEquals(JsonResponse::class, get_class($response));
        $this->assertEquals(IResponse::S200_OK, $response->getHttpCode());

        $payload = $response->getPayload();
        $user = $this->userManager->loadUserByEmail($email);

        $this->assertEquals('taken', $payload['status']);
        $this->assertEquals($email, $payload['email']);
        $this->assertEquals($user->id, $payload['id']);
        $this->assertEquals(false, $payload['password']);
    }

    public function testClaimedUserCorrectPassword()
    {
        $email = 'user@user.sk';
        $_POST['email'] = $email;
        $_POST['password'] = 'password';

        $response = $this->handler->handle(new NoAuthorization());

        $this->assertEquals(JsonResponse::class, get_class($response));
        $this->assertEquals(IResponse::S200_OK, $response->getHttpCode());

        $payload = $response->getPayload();
        $user = $this->userManager->loadUserByEmail($email);

        $this->assertEquals('taken', $payload['status']);
        $this->assertEquals($email, $payload['email']);
        $this->assertEquals($user->id, $payload['id']);
        $this->assertEquals(true, $payload['password']);
    }

    public function testUnclaimedUser()
    {
        $email = 'unclaimed@unclaimed.sk';
        $this->unclaimedUser->createUnclaimedUser($email);
        $_POST['email'] = $email;

        $response = $this->handler->handle(new NoAuthorization());

        $this->assertEquals(JsonResponse::class, get_class($response));
        $this->assertEquals(IResponse::S200_OK, $response->getHttpCode());

        $payload = $response->getPayload();
        $user = $this->userManager->loadUserByEmail($email);

        $this->assertEquals('available', $payload['status']);
        $this->assertEquals($email, $payload['email']);
        $this->assertEquals($user->id, $payload['id']);
        $this->assertEquals(null, $payload['password']);
    }
}
