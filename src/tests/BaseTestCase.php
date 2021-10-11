<?php

namespace Crm\UsersModule\Tests;

use Crm\ApplicationModule\Seeders\CountriesSeeder;
use Crm\ApplicationModule\Tests\DatabaseTestCase;
use Crm\UsersModule\Repositories\DeviceTokensRepository;
use Crm\UsersModule\Repository\AccessTokensRepository;
use Crm\UsersModule\Repository\AddressChangeRequestsRepository;
use Crm\UsersModule\Repository\AddressTypesRepository;
use Crm\UsersModule\Repository\AddressesMetaRepository;
use Crm\UsersModule\Repository\AddressesRepository;
use Crm\UsersModule\Repository\ChangePasswordsLogsRepository;
use Crm\UsersModule\Repository\CountriesRepository;
use Crm\UsersModule\Repository\GroupsRepository;
use Crm\UsersModule\Repository\LoginAttemptsRepository;
use Crm\UsersModule\Repository\PasswordResetTokensRepository;
use Crm\UsersModule\Repository\UserActionsLogRepository;
use Crm\UsersModule\Repository\UserEmailConfirmationsRepository;
use Crm\UsersModule\Repository\UserGroupsRepository;
use Crm\UsersModule\Repository\UserMetaRepository;
use Crm\UsersModule\Repository\UsersRepository;

/**
 * Base database test case for UsersModule
 * Provides all required users repositories, so each database test case doesn't have to list them separately
 * @package Crm\UsersModule\Tests
 */
abstract class BaseTestCase extends DatabaseTestCase
{
    protected function requiredRepositories(): array
    {
        return [
            AccessTokensRepository::class,
            AddressChangeRequestsRepository::class,
            AddressesMetaRepository::class,
            AddressesRepository::class,
            AddressTypesRepository::class,
            ChangePasswordsLogsRepository::class,
            CountriesRepository::class,
            DeviceTokensRepository::class,
            GroupsRepository::class,
            LoginAttemptsRepository::class,
            PasswordResetTokensRepository::class,
            UserActionsLogRepository::class,
            UserEmailConfirmationsRepository::class,
            UserGroupsRepository::class,
            UserMetaRepository::class,
            UsersRepository::class,
        ];
    }

    protected function requiredSeeders(): array
    {
        return [
            CountriesSeeder::class,
        ];
    }
}
