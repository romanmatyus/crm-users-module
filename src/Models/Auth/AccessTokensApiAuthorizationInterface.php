<?php

namespace Crm\UsersModule\Auth;

use Crm\ApiModule\Authorization\ApiAuthorizationInterface;
use Nette\Database\Table\ActiveRow;

interface AccessTokensApiAuthorizationInterface extends ApiAuthorizationInterface
{
    /**
     * @return ActiveRow[]
     */
    public function getAccessTokens();
}
