<?php

namespace Crm\UsersModule\Auth\Repository;

use Crm\ApplicationModule\Repository;
use Nette\Database\Table\ActiveRow;

class AdminUserGroupsRepository extends Repository
{
    protected $tableName = 'admin_user_groups';

    public function removeGroupsForUser(ActiveRow $userRow): int
    {
        return $this->getTable()
            ->where(['user_id' => $userRow->id])
            ->delete();
    }
}
