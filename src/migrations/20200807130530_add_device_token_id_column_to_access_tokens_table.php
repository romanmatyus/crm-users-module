<?php

use Phinx\Migration\AbstractMigration;

class AddDeviceTokenIdColumnToAccessTokensTable extends AbstractMigration
{
    public function change()
    {
        $this->table('access_tokens')
            ->addColumn('device_token_id', 'integer', ['null' => true, 'after' => 'subscription_id'])
            ->addForeignKey(
                'device_token_id',
                'device_tokens',
                'id',
                ['delete'=> 'SET_NULL', 'update'=> 'NO_ACTION']
            )
            ->save();
    }
}
