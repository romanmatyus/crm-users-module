<?php

use Phinx\Migration\AbstractMigration;

class FixConfirmedByAdminValues extends AbstractMigration
{
    public function up()
    {
        $confirmedUserIds = $this->query("
            SELECT `signature`
            FROM `audit_logs`
            WHERE `table_name` = 'users'
              -- change actually done by admin
              AND `user_id` IS NOT NULL
              -- change made after bug commit date
              AND `created_at` >= '2020-07-23'
              -- change to `confirmed_at` date
              AND `data` LIKE '%confirmed_at%'    
        ")->fetchAll();

        $this->execute("
            UPDATE `user_meta`
            SET `value` = '0', `updated_at` = NOW()
            WHERE `key` = 'confirmed_by_admin'
              AND `value` = '1'
              AND `updated_at` >= '2020-07-23'
                
        ");

        if (count($confirmedUserIds)) {
            $userIdsParam = implode(',', $confirmedUserIds);
            $this->execute("
                UPDATE `user_meta`
                SET `value` = '1',
                WHERE `key` = 'confirmed_by_admin'
                  AND `user_id` IN ({$userIdsParam})
            ");
        }
    }

    public function down()
    {
        $this->output->writeln('This is data migration. Down migration is not available.');
    }
}
