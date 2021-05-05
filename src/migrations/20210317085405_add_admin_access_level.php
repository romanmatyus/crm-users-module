<?php

use Phinx\Migration\AbstractMigration;

class AddAdminAccessLevel extends AbstractMigration
{
    public function change()
    {
        $this->table('admin_access')
            ->addColumn('level', 'enum', [
                'null' => true,
                'values' => ['read', 'write'],
                'after' => 'action',
            ])
            ->update();
    }
}
