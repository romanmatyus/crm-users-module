<?php

use Phinx\Migration\AbstractMigration;

class AddUpdatedAtColumnToGroupsTable extends AbstractMigration
{
    public function up()
    {
        $this->table('groups')
            ->addColumn('updated_at', 'datetime', ['null' => true])
            ->update();

        $this->query('UPDATE groups SET updated_at = created_at');

        $this->table('groups')
            ->changeColumn('updated_at', 'datetime', ['null' => false])
            ->update();
    }

    public function down()
    {
        $this->table('groups')
            ->removeColumn('updated_at')
            ->update();
    }
}
