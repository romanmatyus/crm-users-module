<?php

use Phinx\Migration\AbstractMigration;

class CreateUserStatsTable extends AbstractMigration
{
    public function change()
    {
        $this->table('user_stats')
            ->addColumn('user_id', 'integer', ['null' => false])
            ->addColumn('key', 'string', ['null' => false])
            ->addColumn('value', 'string', ['null' => false])
            ->addColumn('created_at', 'datetime', ['null' => false])
            ->addColumn('updated_at', 'datetime', ['null' => false])
            ->addForeignKey('user_id', 'users', 'id')
            ->addIndex(['key', 'value'], ['unique' => true])
            ->create();
    }
}
