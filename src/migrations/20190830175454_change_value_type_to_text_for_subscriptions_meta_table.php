<?php

use Phinx\Migration\AbstractMigration;

class ChangeValueTypeToTextForSubscriptionsMetaTable extends AbstractMigration
{
    public function change()
    {
        $this->table('user_meta')
            ->changeColumn('value', 'text', ['null' => true])
            ->save();
    }
}
