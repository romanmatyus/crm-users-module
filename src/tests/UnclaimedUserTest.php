<?php

namespace Crm\UsersModule\Tests;

use Crm\ApplicationModule\Tests\DatabaseTestCase;
use Crm\UsersModule\Repository\UserMetaRepository;
use Crm\UsersModule\Repository\UsersRepository;
use Crm\UsersModule\User\UnclaimedUser;
use Nette\Database\Table\ActiveRow;

class UnclaimedUserTest extends DatabaseTestCase
{
    /** @var UnclaimedUser */
    private $unclaimedUser;

    protected function requiredRepositories(): array
    {
        return [
            UsersRepository::class,
            UserMetaRepository::class
        ];
    }

    protected function requiredSeeders(): array
    {
        return [];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->unclaimedUser = $this->inject(UnclaimedUser::class);
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
}
