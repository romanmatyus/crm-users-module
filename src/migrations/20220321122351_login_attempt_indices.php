<?php

use Phinx\Migration\AbstractMigration;

class LoginAttemptIndices extends AbstractMigration
{
    public function change()
    {
        $this->table('login_attempts')
            ->removeIndex('os')
            ->removeIndex('device')
            ->addIndex('source')
            ->update();
    }
}
