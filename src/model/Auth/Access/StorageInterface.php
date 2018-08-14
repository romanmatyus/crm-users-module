<?php

namespace Crm\UsersModule\Auth\Access;

interface StorageInterface
{
    public function addToken($token, $type = 'access');

    public function removeToken($token, $type = 'access');

    public function tokenExists($token, $type = 'access');

    public function allTokens($type = 'access');
}
