<?php

use Phinx\Migration\AbstractMigration;

class AddAdminAccessType extends AbstractMigration
{
    public function change()
    {
        $this->table('admin_access')
            ->addColumn('type', 'enum', [
                'null' => true,
                'values' => ['render', 'action', 'handle'],
                'after' => 'action',
            ])
            ->update();
    }
}
