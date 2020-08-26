<?php

namespace Crm\UsersModule\Events;

use League\Event\AbstractEvent;
use Nette\Database\IRow;

class UnpairDeviceAccessTokensEvent extends AbstractEvent
{
    private $deviceToken;

    private $accessToken;

    public function __construct(IRow $deviceToken, IRow $accessToken)
    {
        $this->deviceToken = $deviceToken;
        $this->accessToken = $accessToken;
    }

    public function getDeviceToken(): IRow
    {
        return $this->deviceToken;
    }

    public function getAccessToken(): IRow
    {
        return $this->accessToken;
    }
}
