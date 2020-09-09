<?php

namespace Crm\UsersModule\Tests;

use Crm\UsersModule\Auth\InvalidEmailException;
use Crm\UsersModule\Auth\UserManager;
use Crm\UsersModule\Repository\UserAlreadyExistsException;
use Crm\UsersModule\Repository\UsersRepository;

class UserManagerTest extends BaseTestCase
{
    /** @var UsersRepository */
    private $usersRepository;

    /** @var UserManager */
    private $userManager;

    public function requiredSeeders(): array
    {
        return [];
    }

    public function setUp(): void
    {
        parent::setUp();

        $this->usersRepository = $this->getRepository(UsersRepository::class);
        $this->userManager = $this->inject(UserManager::class);
    }

    public function testAddingNewUser()
    {
        $user = $this->userManager->addNewUser("admin@example.com");
        $this->assertEquals("admin@example.com", $user->email);
        $user = $this->usersRepository->getByEmail("admin@example.com");
        $this->assertEquals("admin@example.com", $user->email);
    }

    public function testAddingExistingUser()
    {
        $this->expectException(UserAlreadyExistsException::class);
        $this->userManager->addNewUser("admin@example.com");
        $this->userManager->addNewUser("admin@example.com");
    }

    public function testAddingInvalidEmail()
    {
        $this->expectException(InvalidEmailException::class);
        $this->userManager->addNewUser('admin');
    }

    public function testAllowingInvalidEmail()
    {
        $user = $this->userManager->addNewUser('admin', false, 'unknown', null, false);
        $this->assertEquals('admin', $user->email);
    }
}
