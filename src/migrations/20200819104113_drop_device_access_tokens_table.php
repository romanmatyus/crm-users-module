<?php

use Phinx\Migration\AbstractMigration;

class DropDeviceAccessTokensTable extends AbstractMigration
{
    public function up()
    {
        $this->table('device_access_tokens')->drop()->save();
    }

    public function down()
    {
        $this->table('device_access_tokens')
            ->addColumn('device_token_id', 'integer', ['null' => false])
            ->addColumn('access_token_id', 'integer', ['null' => false])
            ->addForeignKey('device_token_id', 'device_tokens', 'id')
            ->addForeignKey('access_token_id', 'access_tokens', 'id')
            ->addIndex('device_token_id', ['unique' => false])
            ->addIndex('access_token_id', ['unique' => true])
            ->save();
    }
}
