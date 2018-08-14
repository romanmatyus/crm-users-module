<?php

namespace Crm\UsersModule\Events;

use League\Event\AbstractEvent;

class UserChangePasswordRequestEvent extends AbstractEvent
{
    private $user;

    private $token;

    public function __construct($user, $token)
    {
        $this->user = $user;
        $this->token = $token;
    }

    public function getUser()
    {
        return $this->user;
    }

    public function getToken()
    {
        return $this->token;
    }
}
