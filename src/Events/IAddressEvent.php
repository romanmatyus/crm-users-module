<?php

namespace Crm\UsersModule\Events;

use Nette\Database\Table\ActiveRow;

interface IAddressEvent
{
    public function getAddress(): ActiveRow;

    public function isAdmin(): bool;
}
