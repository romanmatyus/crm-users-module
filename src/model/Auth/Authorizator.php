<?php

namespace Crm\UsersModule\Auth;

use Nette\Security\IAuthorizator;

class Authorizator implements IAuthorizator
{
    private $permissions;

    public function __construct(Permissions $permissions)
    {
        $this->permissions = $permissions;
    }

    public function isAllowed($role, $resource, $privilege): bool
    {
        return $this->permissions->allowed($role, $resource, $privilege);
    }
}
