<?php

use Phinx\Migration\AbstractMigration;

class CreateUserConnectedAccountsTable extends AbstractMigration
{
    public function change()
    {
        $this->table('user_connected_accounts')
            ->addColumn('user_id', 'integer', ['null' => false])
            ->addColumn('type', 'string', ['null' => false])
            ->addColumn('email', 'string', ['null' => false])
            ->addColumn('created_at', 'datetime', ['null' => false])
            ->addColumn('updated_at', 'datetime', ['null' => false])
            ->addColumn('meta', 'json', ['null' => true])
            ->addIndex(['type', 'email'], ['unique' => true])
            ->addForeignKey('user_id', 'users', 'id')
            ->create();
    }
}
