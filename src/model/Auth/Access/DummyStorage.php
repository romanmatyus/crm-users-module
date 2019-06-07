<?php

namespace Crm\UsersModule\Auth\Access;

class DummyStorage implements StorageInterface
{
    public function addToken($token, $type = 'access')
    {
        return true;
    }

    public function removeToken($token, $type = 'access')
    {
        return true;
    }

    public function tokenExists($token, $type = 'access')
    {
        return true;
    }

    public function allTokens($type = 'access')
    {
        return true;
    }
}