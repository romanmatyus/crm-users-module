<?php

use Phinx\Migration\AbstractMigration;

class CreateTableUserEmailConfirmations extends AbstractMigration
{
    public function change()
    {
        $this->table('user_email_confirmations')
            ->addColumn('user_id', 'integer', ['null' => false])
            ->addColumn('token', 'string', ['null' => false])
            ->addColumn('confirmed_at', 'datetime', ['null' => true])
            ->addForeignKey('user_id', 'users', 'id')
            ->save();
    }
}
