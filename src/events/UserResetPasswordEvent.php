<?php

namespace Crm\UsersModule\Events;

use League\Event\AbstractEvent;

class UserResetPasswordEvent extends AbstractEvent
{
    private $user;

    private $newPassword;

    public function __construct($user, $newPassword)
    {
        $this->user = $user;
        $this->newPassword = $newPassword;
    }

    public function getUser()
    {
        return $this->user;
    }

    public function getNewPassword()
    {
        return $this->newPassword;
    }
}
