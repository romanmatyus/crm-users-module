<?php

namespace Crm\UsersModule\Events;

use League\Event\AbstractEvent;

class UserClaimedEvent extends AbstractEvent
{
    private $unclaimedUser;

    private $loggedUser;

    private $deviceToken;

    public function __construct($unclaimedUser, $loggedUser, $deviceToken)
    {
        $this->unclaimedUser = $unclaimedUser;
        $this->loggedUser = $loggedUser;
        $this->deviceToken = $deviceToken;
    }
}
