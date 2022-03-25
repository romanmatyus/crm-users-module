<?php

namespace Crm\UsersModule\Tests;

use Crm\ApiModule\Authorization\NoAuthorization;
use Crm\ApplicationModule\Tests\DatabaseTestCase;
use Crm\UsersModule\Api\EmailValidationApiHandler;
use Crm\UsersModule\Repository\UserMetaRepository;
use Crm\UsersModule\Repository\UsersRepository;
use Crm\UsersModule\Seeders\UsersSeeder;
use Crm\UsersModule\User\UnclaimedUser;
use Tomaj\NetteApi\Response\JsonApiResponse;

class EmailValidationApiHandlerTest extends DatabaseTestCase
{
    /** @var UsersRepository */
    private $usersRepository;

    /** @var EmailValidationApiHandler */
    private $handler;

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
            UserMetaRepository::class,
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->usersRepository = $this->getRepository(UsersRepository::class);
        $this->handler = $this->inject(EmailValidationApiHandler::class);
        $this->unclaimedUser = $this->inject(UnclaimedUser::class);
    }

    public function testSetEmailValidatedExistingUser()
    {
        $user = $this->getUser('test@example.com');
        $_POST['email'] = 'test@example.com';

        $this->handler->setAction('validate');
        $this->handler->setAuthorization(new NoAuthorization());
        $response = $this->handler->handle([]); // TODO: fix params

        $this->assertInstanceOf(JsonApiResponse::class, $response);
        $this->assertEquals($response->getCode(), 200);

        $user = $this->usersRepository->find($user->id);
        $this->assertNotNull($user->email_validated_at);

        // invalidate right away, sunny day scenario

        $this->handler->setAction('invalidate');
        $this->handler->setAuthorization(new NoAuthorization());
        $response = $this->handler->handle([]); // TODO: fix params

        $this->assertInstanceOf(JsonApiResponse::class, $response);
        $this->assertEquals($response->getCode(), 200);

        $user = $this->usersRepository->find($user->id);
        $this->assertNull($user->email_validated_at);
    }

    public function testSetEmailValidatedInvalidUser()
    {
        $_POST['email'] = 'foo@bar.baz';

        $this->handler->setAction('validate');
        $this->handler->setAuthorization(new NoAuthorization());
        $response = $this->handler->handle([]); // TODO: fix params

        $this->assertInstanceOf(JsonApiResponse::class, $response);
        $this->assertEquals($response->getCode(), 404);
        $this->assertEquals('email_not_found', $response->getPayload()['code']);
    }

    public function testSetEmailValidatedNoEmail()
    {
        $this->handler->setAction('validate');
        $this->handler->setAuthorization(new NoAuthorization());
        $response = $this->handler->handle([]); // TODO: fix params

        $this->assertInstanceOf(JsonApiResponse::class, $response);
        $this->assertEquals($response->getCode(), 400);
        $this->assertEquals('invalid_request', $response->getPayload()['code']);
    }

    public function testSetEmailValidatedInvalidEmail()
    {
        $_POST['email'] = 'non_email';

        $this->handler->setAction('validate');
        $this->handler->setAuthorization(new NoAuthorization());
        $response = $this->handler->handle([]); // TODO: fix params

        $this->assertInstanceOf(JsonApiResponse::class, $response);
        $this->assertEquals($response->getCode(), 400);
        $this->assertEquals('invalid_param', $response->getPayload()['code']);
    }

    public function testUnclaimedUser()
    {
        $email = 'unclaimed@unclaimed.sk';
        $this->unclaimedUser->createUnclaimedUser($email);
        $_POST['email'] = $email;

        $this->handler->setAction('validate');
        $this->handler->setAuthorization(new NoAuthorization());
        $response = $this->handler->handle([]); // TODO: fix params

        $this->assertInstanceOf(JsonApiResponse::class, $response);
        $this->assertEquals($response->getCode(), 404);
        $this->assertEquals('email_not_found', $response->getPayload()['code']);
    }

    private function getUser($email)
    {
        return $this->usersRepository->add($email, 'secret');
    }
}
