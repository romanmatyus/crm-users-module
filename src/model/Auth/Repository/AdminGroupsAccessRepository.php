<?php

namespace Crm\UsersModule\Auth\Repository;

use Crm\ApplicationModule\Repository;
use Nette\Database\Table\ActiveRow;

class AdminGroupsAccessRepository extends Repository
{
    protected $tableName = 'admin_groups_access';

    final public function deleteByAdminAccess(ActiveRow $adminAccess): void
    {
        $records = $this->getTable()->where('admin_access_id = ?', $adminAccess->id);
        foreach ($records as $record) {
            $this->delete($record);
        }
    }
}
