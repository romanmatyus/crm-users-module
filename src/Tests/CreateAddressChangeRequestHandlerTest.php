<?php

namespace Crm\UsersModule\Tests;

use Crm\ApiModule\Api\JsonResponse;
use Crm\ApiModule\Authorization\NoAuthorization;
use Crm\ApplicationModule\Seeders\CountriesSeeder;
use Crm\ApplicationModule\Tests\DatabaseTestCase;
use Crm\UsersModule\Api\CreateAddressChangeRequestHandler;
use Crm\UsersModule\Auth\UserManager;
use Crm\UsersModule\Repository\AddressTypesRepository;
use Crm\UsersModule\Repository\AddressesRepository;
use Crm\UsersModule\Repository\UsersRepository;
use Crm\UsersModule\Seeders\UsersSeeder;
use Nette\Http\Response;

class CreateAddressChangeRequestHandlerTest extends DatabaseTestCase
{
    /** @var AddressesRepository */
    private $addressesRepository;

    /** @var CreateAddressChangeRequestHandler */
    private $handler;

    /** @var UserManager */
    private $userManager;

    /** @var AddressTypesRepository */
    private $addressTypesRepository;

    protected function requiredSeeders(): array
    {
        return [
            CountriesSeeder::class,
            UsersSeeder::class
        ];
    }

    protected function requiredRepositories(): array
    {
        return [
            AddressesRepository::class,
            AddressTypesRepository::class,
            UsersRepository::class,
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->handler = $this->inject(CreateAddressChangeRequestHandler::class);
        $this->addressesRepository = $this->getRepository(AddressesRepository::class);
        $this->userManager = $this->inject(UserManager::class);
        $this->addressTypesRepository = $this->getRepository(AddressTypesRepository::class);

        $this->addressTypesRepository->add('test', 'Test');

        unset($_POST);
    }

    public function testRequiredMissing()
    {
        $this->handler->setAuthorization(new NoAuthorization());
        $response = $this->handler->handle([]); // TODO: fix params

        $this->assertEquals(JsonResponse::class, get_class($response));
        $this->assertEquals(Response::S400_BAD_REQUEST, $response->getHttpCode());

        $payload = $response->getPayload();
        $this->assertEquals('error', $payload['status']);
    }

    public function testUserNotFound()
    {
        $_POST['email'] = '0test@user.site';
        $_POST['type'] = 'test';

        $this->handler->setAuthorization(new NoAuthorization());
        $response = $this->handler->handle([]); // TODO: fix params

        $this->assertEquals(JsonResponse::class, get_class($response));
        $this->assertEquals(Response::S404_NOT_FOUND, $response->getHttpCode());

        $payload = $response->getPayload();
        $this->assertEquals('error', $payload['status']);
        $this->assertEquals('User not found', $payload['message']);
    }

    public function testTypeNotFound()
    {
        $_POST['email'] = 'admin@admin.sk';
        $_POST['type'] = '@test';

        $this->handler->setAuthorization(new NoAuthorization());
        $response = $this->handler->handle([]); // TODO: fix params

        $this->assertEquals(JsonResponse::class, get_class($response));
        $this->assertEquals(Response::S400_BAD_REQUEST, $response->getHttpCode());

        $payload = $response->getPayload();
        $this->assertEquals('error', $payload['status']);
        $this->assertEquals('Address type not found', $payload['message']);
    }

    public function testCountryNotFound()
    {
        $_POST['email'] = 'admin@admin.sk';
        $_POST['type'] = 'test';

        $_POST['country_iso'] = 'QQQ';

        $this->handler->setAuthorization(new NoAuthorization());
        $response = $this->handler->handle([]); // TODO: fix params

        $this->assertEquals(JsonResponse::class, get_class($response));
        $this->assertEquals(Response::S400_BAD_REQUEST, $response->getHttpCode());

        $payload = $response->getPayload();
        $this->assertEquals('error', $payload['status']);
        $this->assertEquals('Country not found', $payload['message']);
    }

    public function testParentAddressNotFound()
    {
        $_POST['email'] = 'admin@admin.sk';
        $_POST['type'] = 'test';

        $this->handler->setAuthorization(new NoAuthorization());
        $response = $this->handler->handle([]); // TODO: fix params

        $this->assertEquals(JsonResponse::class, get_class($response));
        $this->assertEquals(Response::S404_NOT_FOUND, $response->getHttpCode());

        $payload = $response->getPayload();
        $this->assertEquals('error', $payload['status']);
        $this->assertEquals('Parent address not found', $payload['message']);
    }

    public function testValid()
    {
        $_POST['email'] = 'admin@admin.sk';
        $_POST['type'] = 'test';

        $_POST['address'] = 'Vysoka';
        $_POST['city'] = 'Poprad';
        $_POST['zip'] = '98745';
        $_POST['country_iso'] = 'AU';

        $user = $this->userManager->loadUserByEmail($_POST['email']);
        $this->addressesRepository->add($user, $_POST['type'], null, null, $_POST['address'], null, $_POST['city'], $_POST['zip'], null, null);

        $this->handler->setAuthorization(new NoAuthorization());
        $response = $this->handler->handle([]); // TODO: fix params

        $this->assertEquals(JsonResponse::class, get_class($response));
        $this->assertEquals(Response::S200_OK, $response->getHttpCode());

        $payload = $response->getPayload();
        $this->assertEquals('ok', $payload['status']);
    }
}
