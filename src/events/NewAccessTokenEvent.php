<?php

namespace Crm\UsersModule\Events;

use League\Event\AbstractEvent;

class NewAccessTokenEvent extends AbstractEvent
{
    private $userId;

    private $token;

    public function __construct(int $userId, string $token)
    {
        $this->userId = $userId;
        $this->token = $token;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function getToken(): string
    {
        return $this->token;
    }
}
