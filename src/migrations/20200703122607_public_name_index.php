<?php

use Phinx\Migration\AbstractMigration;

class PublicNameIndex extends AbstractMigration
{
    public function change()
    {
        $this->table('users')
            ->addIndex('public_name')
            ->update();
    }
}
