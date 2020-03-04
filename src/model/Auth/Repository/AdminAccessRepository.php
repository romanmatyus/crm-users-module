<?php

namespace Crm\UsersModule\Auth\Repository;

use Crm\ApplicationModule\Repository;
use Nette\Utils\DateTime;

class AdminAccessRepository extends Repository
{
    protected $tableName = 'admin_access';

    final public function add($resource, $action)
    {
        return $this->insert([
            'resource' => $resource,
            'action' => $action,
            'created_at' => new DateTime(),
            'updated_at' => new DateTime(),
        ]);
    }

    final public function exists($resource, $action)
    {
        return $this->getTable()->where(['resource' => $resource, 'action' => $action])->count('*') > 0;
    }

    final public function all()
    {
        return $this->getTable()->order('resource');
    }
}
