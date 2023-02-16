<?php

namespace Crm\UsersModule\Events;

use Nette\Database\Table\ActiveRow;

interface UserEventInterface
{
    public function getUser(): ActiveRow;
}
