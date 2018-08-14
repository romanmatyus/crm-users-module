<?php

namespace Crm\UsersModule\Events;

use League\Event\AbstractEvent;

class UserConfirmedEvent extends AbstractEvent
{
    private $user;

    public function __construct($user)
    {
        $this->user = $user;
    }

    public function getUser()
    {
        return $this->user;
    }
}
