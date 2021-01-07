<?php

use Phinx\Migration\AbstractMigration;

class RemoveConfirmedByAdminWithFalseValue extends AbstractMigration
{
    public function up()
    {
        $sql = <<<SQL
            DELETE FROM `user_meta`
            WHERE
               `user_meta`.`key` = 'confirmed_by_admin'
                AND `user_meta`.`value` = '0';
SQL;

        $this->execute($sql);
    }

    public function down()
    {
        $this->output->writeln('This is data migration. Down migration is not available.');
    }
}
