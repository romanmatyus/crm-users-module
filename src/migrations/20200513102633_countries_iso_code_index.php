<?php

use Phinx\Migration\AbstractMigration;

class CountriesIsoCodeIndex extends AbstractMigration
{
    public function change()
    {
        $this->table('countries')
            ->changeColumn('iso_code', 'string', ['null' => false])
            ->addIndex('iso_code', ['unique' => true])
            ->update();
    }
}
