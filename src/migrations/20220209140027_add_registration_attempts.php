<?php

use Phinx\Migration\AbstractMigration;

class AddRegistrationAttempts extends AbstractMigration
{
    public function change()
    {
        $this->table('registration_attempts')
            ->addColumn('user_id', 'integer', ['null' => true])
            ->addColumn('email', 'string', ['null' => false])
            ->addColumn('created_at', 'datetime', ['null' => false])
            ->addColumn('status', 'string', ['null' => false])
            ->addColumn('source', 'string', ['null' => true])
            ->addColumn('ip', 'string', ['null' => false])
            ->addColumn('user_agent', 'text', ['null' => false])
            ->addColumn('os', 'string')
            ->addColumn('device', 'string')
            ->addForeignKey('user_id', 'users')
            ->addIndex(['ip', 'created_at'])
            ->create();
    }
}
