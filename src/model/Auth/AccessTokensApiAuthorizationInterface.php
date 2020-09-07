<?php

namespace Crm\UsersModule\Auth;

use Crm\ApiModule\Authorization\ApiAuthorizationInterface;

interface AccessTokensApiAuthorizationInterface extends ApiAuthorizationInterface
{
    public function getAccessTokens();
}
