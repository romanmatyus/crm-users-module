<?php

namespace Crm\UsersModule\Auth;

use Crm\ApiModule\Authorization\ApiAuthorizationInterface;

interface UsersApiAuthorizationInterface extends ApiAuthorizationInterface
{
    public function getAuthorizedUsers();

    public function getAccessTokens();
}
