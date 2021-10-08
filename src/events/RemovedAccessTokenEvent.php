<?php

namespace Crm\UsersModule\Events;

use League\Event\AbstractEvent;

class RemovedAccessTokenEvent extends AbstractEvent
{
    private $userId;

    private $token;

    private $source;

    public function __construct(int $userId, string $accessToken, ?string $source = null)
    {
        $this->userId = $userId;
        $this->token = $accessToken;
        $this->source = $source;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function getAccessToken(): string
    {
        return $this->token;
    }

    public function getSource(): ?string
    {
        return $this->source;
    }
}
