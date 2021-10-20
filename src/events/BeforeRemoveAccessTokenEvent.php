<?php

namespace Crm\UsersModule\Events;

use League\Event\AbstractEvent;
use Nette\Database\Table\ActiveRow;

class BeforeRemoveAccessTokenEvent extends AbstractEvent
{
    private ActiveRow $accessToken;

    public function __construct(ActiveRow $accessToken)
    {
        $this->accessToken = $accessToken;
    }

    public function getAccessToken(): ActiveRow
    {
        return $this->accessToken;
    }
}
