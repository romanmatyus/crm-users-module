<?php

namespace Crm\UsersModule\Events;

use League\Event\AbstractEvent;

class RemovedAccessTokenEvent extends AbstractEvent
{
    private $userId;

    private $token;

    public function __construct($userId, $token)
    {
        $this->userId = $userId;
        $this->token = $token;
    }

    public function getUserId()
    {
        return $this->userId;
    }

    public function getToken()
    {
        return $this->token;
    }
}
