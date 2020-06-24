<?php

use Phinx\Migration\AbstractMigration;

class CreateDeviceTokensTable extends AbstractMigration
{
    public function change()
    {
        $this->table('device_tokens')
            ->addColumn('device_id', 'string', ['null' => false])
            ->addColumn('created_at', 'datetime', ['null' => false])
            ->addColumn('last_used_at', 'datetime', ['null' => false])
            ->addColumn('token', 'string', ['null' => false])
            ->addIndex('token', ['unique' => true])
            ->addIndex('device_id', ['unique' => false])
            ->save();
    }
}
