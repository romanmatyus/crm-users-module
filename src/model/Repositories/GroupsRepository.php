<?php

namespace Crm\UsersModule\Repository;

use Crm\ApplicationModule\Repository;
use DateTime;
use Nette\Database\Table\IRow;
use Nette\Database\Table\Selection;

class GroupsRepository extends Repository
{
    protected $tableName = 'groups';

    public function add($groupName, $sorting = 10)
    {
        return $this->insert([
            'name' => $groupName,
            'sorting' => $sorting,
            'created_at' => new \DateTime(),
        ]);
    }

    /**
     * @return Selection
     */
    public function all()
    {
        return $this->getTable()->order('sorting');
    }

    public function update(IRow &$row, $data)
    {
        $data['updated_at'] = new DateTime();
        return parent::update($row, $data);
    }
}
