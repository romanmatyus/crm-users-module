<?php

use Phinx\Migration\AbstractMigration;

class AddressMetaToExtension extends AbstractMigration
{
    public function change()
    {
        if (!$this->hasTable('addresses_meta')) {
            $this->table('addresses_meta')
                ->addColumn('address_id', 'integer', ['null' => false])
                ->addColumn('address_change_request_id', 'integer', ['null' => false])
                ->addColumn('key', 'string', ['null' => false])
                ->addColumn('value', 'string', ['null' => false])
                ->addTimestamps()
                ->addForeignKey('address_id', 'addresses')
                ->addForeignKey('address_change_request_id', 'address_change_requests')
                ->create();
        }
    }
}
