<?php

namespace Crm\UsersModule\Auth;

use Crm\ApiModule\Authorization\ApiAuthorizationInterface;
use Nette\Database\Table\IRow;

interface AccessTokensApiAuthorizationInterface extends ApiAuthorizationInterface
{
    /**
     * @return IRow[]
     */
    public function getAccessTokens();
}
