<?php

namespace Crm\UsersModule\Events;

use League\Event\AbstractEvent;

class UserSignInEvent extends AbstractEvent
{
    const SOURCE_WEB = 'web';
    const SOURCE_API = 'api';

    private $user;

    private $source;

    public function __construct($user, $source = self::SOURCE_WEB)
    {
        $this->user = $user;
        $this->source = $source;
    }

    public function getUser()
    {
        return $this->user;
    }

    public function getSource()
    {
        return $this->source;
    }
}
