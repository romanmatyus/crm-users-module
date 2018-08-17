<?php

namespace Crm\UsersModule\Events;

use League\Event\AbstractEvent;
use Nette\Database\Table\ActiveRow;

class NewAddressEvent extends AbstractEvent implements IAddressEvent
{
    private $address;

    private $admin;

    public function __construct(ActiveRow $address, bool $admin = false)
    {
        $this->address = $address;
        $this->admin = $admin;
    }

    public function getAddress(): ActiveRow
    {
        return $this->address;
    }

    public function isAdmin(): bool
    {
        return $this->admin;
    }
}
