<?php

namespace Crm\UsersModule\Events;

use Crm\UsersModule\User\UserData;
use League\Event\AbstractListener;
use League\Event\EventInterface;

class NewAccessTokenHandler extends AbstractListener
{
    private $userData;

    public function __construct(UserData $userData)
    {
        $this->userData = $userData;
    }

    public function handle(EventInterface $event)
    {
        $userId = $event->getUserId();
        $this->userData->refreshUserTokens($userId);
    }
}
