<?php

namespace Crm\UsersModule\Events;

use League\Event\AbstractEvent;
use Nette\Database\Table\ActiveRow;

class PairDeviceAccessTokensEvent extends AbstractEvent
{
    private $deviceToken;

    private $accessToken;

    public function __construct(ActiveRow $deviceToken, ActiveRow $accessToken)
    {
        $this->deviceToken = $deviceToken;
        $this->accessToken = $accessToken;
    }

    public function getDeviceToken(): ActiveRow
    {
        return $this->deviceToken;
    }

    public function getAccessToken(): ActiveRow
    {
        return $this->accessToken;
    }
}
