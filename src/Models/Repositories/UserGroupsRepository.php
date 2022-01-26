<?php

namespace Crm\UsersModule\Repository;

use Crm\ApplicationModule\Repository;
use Nette\Database\Context;
use Nette\Database\Table\IRow;

class UserGroupsRepository extends Repository
{
    protected $tableName = 'user_groups';

    private $usersRepository;

    private $groupsRepository;

    public function __construct(Context $database, UsersRepository $usersRepository, GroupsRepository $groupsRepository)
    {
        parent::__construct($database);
        $this->usersRepository = $usersRepository;
        $this->groupsRepository = $groupsRepository;
    }

    final public function isMember(IRow $groupRow, IRow $userRow)
    {
        return $this->row($groupRow, $userRow)->count('*') > 0;
    }

    final public function addToGroup(IRow $groupRow, IRow $userRow)
    {
        if ($this->isMember($groupRow, $userRow)) {
            return false;
        }

        $this->insert([
            'group_id' => $groupRow->id,
            'user_id' => $userRow->id,
            'created_at' => new \DateTime(),
        ]);

        return true;
    }

    final public function removeFromGroup(IRow $groupRow, IRow $userRow)
    {
        if (!$this->isMember($groupRow, $userRow)) {
            return false;
        }

        $this->row($groupRow, $userRow)->delete();

        return true;
    }

    final public function userGroups(IRow $userRow)
    {
        return $this->groupsRepository->getTable()->where([
            ':user_groups.user_id' => $userRow->id,
        ])->order(':user_groups.created_at');
    }

    final public function groupMembers(IRow $groupRow)
    {
        return $this->usersRepository->getTable()->where([
            ':user_groups.group_id' => $groupRow->id,
        ])->order(':user_groups.created_at');
    }

    private function row(IRow $groupRow, IRow $userRow)
    {
        return $this->getTable()->where(['user_id' => $userRow->id, 'group_id' => $groupRow->id]);
    }
}
