<?php

namespace Crm\UsersModule\Events;

use League\Event\AbstractEvent;
use Nette\Database\IRow;

class BeforeRemoveAccessTokenEvent extends AbstractEvent
{
    private $accessToken;

    public function __construct(IRow $accessToken)
    {
        $this->accessToken = $accessToken;
    }

    public function getAccessToken(): IRow
    {
        return $this->accessToken;
    }
}
