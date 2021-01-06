<?php

use Phinx\Migration\AbstractMigration;

class FixConfirmedByAdminValues extends AbstractMigration
{
    public function up()
    {
        $sql = <<<SQL
            UPDATE `user_meta`
            LEFT JOIN `audit_logs`
               ON `audit_logs`.`signature` = `user_meta`.`user_id`
               AND `audit_logs`.`table_name` = 'users'
               -- change done by admin
               AND `audit_logs`.`user_id` IS NOT NULL
               -- change after commit date of "broken" change
               AND `audit_logs`.`created_at` >= '2020-07-23'
               -- change to `confirmed_at` date
               AND `audit_logs`.`data` LIKE '%confirmed_at%'
            SET `user_meta`.`value` = '0'
            WHERE
               `user_meta`.`key` = 'confirmed_by_admin'
                AND `user_meta`.`value` = '1'
                AND `audit_logs`.`id` IS NULL;
SQL;

        $this->execute($sql);
    }

    public function down()
    {
        $this->output->writeln('This is data migration. Down migration is not available.');
    }
}
