<?php

namespace Crm\UsersModule\Events;

use League\Event\AbstractEvent;

class RemovedAccessTokenEvent extends AbstractEvent
{
    private $userId;

    private $token;

    private $types;

    public function __construct(int $userId, string $token, array $types = [])
    {
        $this->userId = $userId;
        $this->token = $token;
        $this->types = $types;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function getTypes(): array
    {
        return $this->types;
    }
}
