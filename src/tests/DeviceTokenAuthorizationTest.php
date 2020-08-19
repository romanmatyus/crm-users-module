<?php

namespace Crm\UsersModule\Tests;

use Crm\ApiModule\Repository\UserSourceAccessesRepository;
use Crm\ApplicationModule\Tests\DatabaseTestCase;
use Crm\CoverpageModule\Api\Repository\CoverpageApiLogsRepository;
use Crm\MailModule\Mailer\Repository\MailUserSubscriptionsRepository;
use Crm\UsersModule\Auth\AutoLogin\Repository\AutoLoginTokensRepository;
use Crm\UsersModule\Auth\DeviceTokenAuthorization;
use Crm\UsersModule\Auth\Repository\AdminUserGroupsRepository;
use Crm\UsersModule\Repositories\DeviceTokensRepository;
use Crm\UsersModule\Repository\AccessTokensRepository;
use Crm\UsersModule\Repository\ChangePasswordsLogsRepository;
use Crm\UsersModule\Repository\UserActionsLogRepository;
use Crm\UsersModule\Repository\UserGroupsRepository;
use Crm\UsersModule\Repository\UserMetaRepository;
use Crm\UsersModule\Repository\UsersRepository;

class DeviceTokenAuthorizationTest extends DatabaseTestCase
{
    /** @var DeviceTokensRepository */
    private $deviceTokensRepository;

    /** @var UsersRepository */
    private $usersRepository;

    /** @var AccessTokensRepository */
    private $accessTokensRepository;

    /** @var DeviceTokenAuthorization */
    private $deviceTokenAuthorization;

    protected function requiredRepositories(): array
    {
        return [
            DeviceTokensRepository::class,
            AutoLoginTokensRepository::class,
            ChangePasswordsLogsRepository::class,
            CoverpageApiLogsRepository::class,
            UserSourceAccessesRepository::class,
            UserGroupsRepository::class,
            AdminUserGroupsRepository::class,
            AccessTokensRepository::class,
            MailUserSubscriptionsRepository::class,
            UserActionsLogRepository::class,
            UserMetaRepository::class,
            UsersRepository::class
        ];
    }

    protected function requiredSeeders(): array
    {
        return [];
    }

    public function setUp(): void
    {
        parent::setUp();

        $this->deviceTokensRepository = $this->container->getByType('Crm\UsersModule\Repositories\DeviceTokensRepository');
        $this->usersRepository = $this->container->getByType('Crm\UsersModule\Repository\UsersRepository');
        $this->accessTokensRepository = $this->container->getByType('Crm\UsersModule\Repository\AccessTokensRepository');

        $this->deviceTokenAuthorization = $this->container->getByType('Crm\UsersModule\Auth\DeviceTokenAuthorization');
    }

    public function testNotExistingDeviceToken()
    {
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer devtok_notexistingtoken';

        $this->assertFalse($this->deviceTokenAuthorization->authorized());

        unset($_SERVER['HTTP_AUTHORIZATION']);
    }

    public function testAuthorizedWithDeviceTokens()
    {
        $deviceToken = $this->deviceTokensRepository->add('test_dev_id');

        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $deviceToken->token;

        $this->assertTrue($this->deviceTokenAuthorization->authorized());
        $this->assertEmpty($this->deviceTokenAuthorization->getAuthorizedUsers());
        $this->assertEmpty($this->deviceTokenAuthorization->getAccessTokens());

        unset($_SERVER['HTTP_AUTHORIZATION']);
    }

    public function testAuthorizedUsersAndTokens()
    {
        $user1 = $this->usersRepository->add('test1@user.com', 'nbusr123', '', '');
        $accessToken1 = $this->accessTokensRepository->add($user1, 3);
        $user2 = $this->usersRepository->add('test2@user.com', 'nbusr123', '', '');
        $accessToken2 = $this->accessTokensRepository->add($user2, 3);
        $accessToken3 = $this->accessTokensRepository->add($user2, 3);

        $deviceToken = $this->deviceTokensRepository->add('test_dev_id');

        $this->accessTokensRepository->pairWithDeviceToken($accessToken1, $deviceToken);
        $this->accessTokensRepository->pairWithDeviceToken($accessToken2, $deviceToken);
        $this->accessTokensRepository->pairWithDeviceToken($accessToken3, $deviceToken);

        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $deviceToken->token;

        $this->assertTrue($this->deviceTokenAuthorization->authorized());

        $authorizedUsers = $this->deviceTokenAuthorization->getAuthorizedUsers();
        $this->assertCount(2, $authorizedUsers);

        $authorizedTokens = $this->deviceTokenAuthorization->getAccessTokens();
        $this->assertCount(3, $authorizedTokens);
    }
}
