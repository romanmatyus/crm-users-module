<?php

namespace Crm\UsersModule\User;

/**
 * @deprecated use Crm\UsersModule\Events\UserEventInterface instead.
 */
interface IUserGetter
{
    public function getUserId(): int;
}
