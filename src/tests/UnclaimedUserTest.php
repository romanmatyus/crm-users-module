<?php

namespace Crm\UsersModule\Tests;

use Crm\ApplicationModule\Tests\DatabaseTestCase;
use Crm\UsersModule\Auth\Access\AccessTokenNotFoundException;
use Crm\UsersModule\Repositories\DeviceTokensRepository;
use Crm\UsersModule\Repository\AccessTokensRepository;
use Crm\UsersModule\Repository\UserMetaRepository;
use Crm\UsersModule\Repository\UsersRepository;
use Crm\UsersModule\Seeders\UsersSeeder;
use Crm\UsersModule\User\ClaimedUserException;
use Crm\UsersModule\User\UnclaimedUser;
use Crm\UsersModule\User\UnclaimedUserException;
use Nette\Database\Table\ActiveRow;

class UnclaimedUserTest extends DatabaseTestCase
{
    /** @var UnclaimedUser */
    private $unclaimedUser;

    /** @var AccessTokensRepository */
    private $accessTokensRepository;

    /** @var DeviceTokensRepository */
    private $deviceTokensRepository;

    /** @var UsersRepository */
    private $usersRepository;

    private $unclaimedUserObj;

    private $loggedUser;

    private $deviceToken;

    protected function requiredRepositories(): array
    {
        return [
            AccessTokensRepository::class,
            DeviceTokensRepository::class,
            UsersRepository::class,
            UserMetaRepository::class
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

        $this->unclaimedUser = $this->inject(UnclaimedUser::class);
        $this->usersRepository = $this->getRepository(UsersRepository::class);
        $this->accessTokensRepository = $this->getRepository(AccessTokensRepository::class);
        $this->deviceTokensRepository = $this->getRepository(DeviceTokensRepository::class);

        $this->unclaimedUserObj = $this->unclaimedUser->createUnclaimedUser();
        $this->loggedUser = $this->usersRepository->getByEmail('admin@admin.sk');
        $this->deviceToken = $this->deviceTokensRepository->add('device');
    }

    public function testCreateUnclaimedUserWithoutEmail()
    {
        $user = $this->unclaimedUser->createUnclaimedUser();

        $this->assertIsObject($user);
        $this->assertEquals(ActiveRow::class, get_class($user));
        $this->assertTrue($this->unclaimedUser->isUnclaimedUser($user));
    }

    public function testCreateUnclaimedUserWithEmail()
    {
        $email = 'unclaimed@user.com';
        $user = $this->unclaimedUser->createUnclaimedUser($email);

        $this->assertIsObject($user);
        $this->assertEquals(ActiveRow::class, get_class($user));
        $this->assertEquals($email, $user->email);
        $this->assertTrue($this->unclaimedUser->isUnclaimedUser($user));
    }

    public function testClaimUserClaimedUserException()
    {
        $this->expectException(ClaimedUserException::class);
        $this->unclaimedUser->claimUser($this->loggedUser, $this->unclaimedUserObj, $this->deviceToken);
    }

    public function testClaimUserUnclaimedUserException()
    {
        $this->expectException(UnclaimedUserException::class);
        $this->unclaimedUser->claimUser($this->unclaimedUserObj, $this->unclaimedUserObj, $this->deviceToken);
    }

    public function testClaimUserAccessTokenNotFound()
    {
        $this->expectException(AccessTokenNotFoundException::class);
        $this->unclaimedUser->claimUser($this->unclaimedUserObj, $this->loggedUser, $this->deviceToken);
    }
}
