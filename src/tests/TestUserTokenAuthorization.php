<?php

namespace Crm\UsersModule\Tests;

use Crm\ApiModule\Authorization\ApiAuthorizationInterface;
use Nette\Security\IAuthorizator;

class TestUserTokenAuthorization implements ApiAuthorizationInterface
{
    private $token;

    public function __construct($token)
    {
        $this->token = $token;
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
            'token' => $this->token
        ];
    }
}
