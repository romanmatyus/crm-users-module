<?php

namespace Crm\UsersModule\Events;

use League\Event\AbstractEvent;

class UserCreatedEvent extends AbstractEvent
{
    private $user;

    private $originalPassword;

    private $sendEmail;

    public function __construct($user, $originalPassword, $sendEmail = false)
    {
        $this->user = $user;
        $this->originalPassword = $originalPassword;
        $this->sendEmail = $sendEmail;
    }

    public function getUser()
    {
        return $this->user;
    }

    public function sendEmail()
    {
        return $this->sendEmail;
    }

    public function getOriginalPassword()
    {
        return $this->originalPassword;
    }
}
