<?php

namespace Crm\UsersModule\Events;

use Crm\UsersModule\Auth\UserAuthenticator;
use League\Event\AbstractListener;
use League\Event\EventInterface;
use Nette\Security\User;

class UserUpdatedHandler extends AbstractListener
{
    private $user;

    private $userAuthenticator;

    public function __construct(User $user, UserAuthenticator $userAuthenticator)
    {
        $this->user = $user;
        $this->userAuthenticator = $userAuthenticator;
    }

    public function handle(EventInterface $event)
    {
        if (!($event instanceof UserUpdatedEvent)) {
            throw new \Exception('cannot handle event, invalid instance received: ' . gettype($event));
        }

        $updatedUser = $event->getUser();

        // If updated user is currently logged user, update his/her session data
        if ($this->user->isLoggedIn() && $updatedUser->id == $this->user->getId()) {
            $this->user->getStorage()->saveAuthentication($this->userAuthenticator->getIdentity($updatedUser));
        }
    }
}
