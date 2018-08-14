<?php

namespace Crm\UsersModule\Events;

use Crm\UsersModule\Auth\UserManager;
use League\Event\AbstractListener;
use League\Event\EventInterface;

class UserMailHandler extends AbstractListener
{
    private $userManager;

    public function __construct(UserManager $userManager)
    {
        $this->userManager = $userManager;
    }

    public function handle(EventInterface $event)
    {
        if ($event->getType() != 'delivered') {
            return;
        }

        $user = $this->userManager->loadUserByEmail($event->getEmail());
        if ($user && !$user->confirmed_at) {
            $this->userManager->confirmUser($user);
        }
    }
}
