<?php

namespace Crm\UsersModule\Events;

use League\Event\AbstractEvent;

class UserChangePasswordEvent extends AbstractEvent
{
    private $user;

    private $newPassword;

    private $notify;

    public function __construct($user, $newPassword, $notify = true)
    {
        $this->user = $user;
        $this->newPassword = $newPassword;
        $this->notify = $notify;
    }

    public function getUser()
    {
        return $this->user;
    }

    public function shouldNotify()
    {
        return $this->notify;
    }

    public function getNewPassword()
    {
        return $this->newPassword;
    }
}
