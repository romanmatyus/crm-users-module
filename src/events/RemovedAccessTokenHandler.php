<?php

namespace Crm\UsersModule\Events;

use Crm\UsersModule\User\UserData;
use League\Event\AbstractListener;
use League\Event\EventInterface;

class RemovedAccessTokenHandler extends AbstractListener
{
    private $userData;

    public function __construct(UserData $userData)
    {
        $this->userData = $userData;
    }

    public function handle(EventInterface $event)
    {
        $token = $event->getToken();
        $this->userData->removeUserToken($token);
    }
}
