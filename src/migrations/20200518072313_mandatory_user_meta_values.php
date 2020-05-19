<?php

use Phinx\Migration\AbstractMigration;

class MandatoryUserMetaValues extends AbstractMigration
{
    public function change()
    {
        $this->query('DELETE FROM user_meta WHERE value IS NULL');
        $this->table('user_meta')
            ->changeColumn('value', 'text', ['null' => false])
            ->update();
    }
}
