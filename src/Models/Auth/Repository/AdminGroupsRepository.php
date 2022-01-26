<?php

namespace Crm\UsersModule\Auth\Repository;

use Crm\ApplicationModule\Repository;
use Nette\Database\Table\IRow;
use Nette\Utils\DateTime;

class AdminGroupsRepository extends Repository
{
    protected $tableName = 'admin_groups';

    final public function add($name, $sorting = 100)
    {
        return $this->insert([
            'name' => $name,
            'sorting' => $sorting,
            'created_at' => new DateTime(),
            'updated_at' => new DateTime(),
        ]);
    }

    final public function update(IRow &$row, $data)
    {
        $data['updated_at'] = new DateTime();
        return parent::update($row, $data);
    }

    final public function findByName($name)
    {
        return $this->getTable()->where(['name' => $name])->limit(1)->fetch();
    }

    final public function all()
    {
        return $this->getTable()->order('sorting ASC');
    }
}
