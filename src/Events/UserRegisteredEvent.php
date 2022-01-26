<?php

namespace Crm\UsersModule\Events;

use League\Event\AbstractEvent;

/**
 * UserRegisteredEvent is emitted when new user is registered during standard registration flow.
 * Unlike NewUserEvent there is option in UserBuilder to disable event emitting.
 *
 * States the user can go through: new - REGISTERED (this) - disabled - deleted.
 */
class UserRegisteredEvent extends AbstractEvent
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
