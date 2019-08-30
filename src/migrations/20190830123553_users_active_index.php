<?php

use Phinx\Migration\AbstractMigration;

class UsersActiveIndex extends AbstractMigration
{
    public function change()
    {
        $this->table('users')
            ->addIndex('active')
            ->update();
    }
}
