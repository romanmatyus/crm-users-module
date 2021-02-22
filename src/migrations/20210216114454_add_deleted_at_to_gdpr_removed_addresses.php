<?php

use Phinx\Migration\AbstractMigration;

class AddDeletedAtToGdprRemovedAddresses extends AbstractMigration
{
    public function up()
    {
        $sql = <<<SQL
        UPDATE addresses SET deleted_at = updated_at 
        WHERE first_name = 'GDPR removal' AND last_name = 'GDPR removal' AND address = 'GDPR removal'
SQL;
        $this->execute($sql);
    }

    public function down()
    {
        $this->output->writeln('Down migration is not available.');
    }
}
