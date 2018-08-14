<?php

namespace Crm\UsersModule\Events;

use League\Event\AbstractEvent;
use Nette\Database\Table\ActiveRow;

class AddressChangedEvent extends AbstractEvent implements IAddressEvent
{
    private $address;

    public function __construct(ActiveRow $address)
    {
        $this->address = $address;
    }

    public function getAddress(): ActiveRow
    {
        return $this->address;
    }
}
