<?php

namespace Crm\UsersModule\Tests;

use Crm\UsersModule\Auth\UsersApiAuthorizationInterface;
use Nette\Security\IAuthorizator;

class TestUserTokenAuthorization implements UsersApiAuthorizationInterface
{
    private $users = [];

    private $tokens = [];

    public function __construct($token, $user = null)
    {
        $this->tokens[] = $token;

        if ($user !== null) {
            $this->users[] = $user;
        }
    }

    public function authorized($resource = IAuthorizator::ALL)
    {
        return true;
    }

    public function getErrorMessage()
    {
        return false;
    }

    public function getAuthorizedData()
    {
        return [
            'token' => reset($this->tokens)
        ];
    }

    public function getAuthorizedUsers()
    {
        return $this->users;
    }

    public function getAccessTokens()
    {
        return $this->tokens;
    }
}
