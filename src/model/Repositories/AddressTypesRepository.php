<?php

namespace Crm\UsersModule\Repository;

use Crm\ApplicationModule\Repository;

class AddressTypesRepository extends Repository
{
    protected $tableName = 'address_types';

    final public function getPairs()
    {
        return $this->getTable()->order('sorting')->fetchPairs('type', 'title');
    }
}
