<?php

namespace Crm\UsersModule\Events;

use League\Event\AbstractEvent;

class UserChangePasswordEvent extends AbstractEvent
{
    private $user;

    private $notify;

    public function __construct($user, $notify = true)
    {
        $this->user = $user;
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
}
