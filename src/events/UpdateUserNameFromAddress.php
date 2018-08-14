<?php

namespace Crm\UsersModule\Events;

use Crm\UsersModule\Repository\UsersRepository;
use League\Event\AbstractListener;
use League\Event\EventInterface;

class UpdateUserNameFromAddress extends AbstractListener
{
    private $usersRepository;

    public function __construct(UsersRepository $usersRepository)
    {
        $this->usersRepository = $usersRepository;
    }

    public function handle(EventInterface $event)
    {
        $address = $event->getAddress();
        $user = $address->user;

        if (!$user->first_name && $address->first_name) {
            $this->usersRepository->update($user, ['first_name' => $address->first_name]);
        }

        if (!$user->last_name && $address->last_name) {
            $this->usersRepository->update($user, ['last_name' => $address->last_name]);
        }
    }
}
