<?php

use Phinx\Migration\AbstractMigration;

class FixUserStatsTableUniqueIndex extends AbstractMigration
{
    public function up()
    {
        // TRUNCATE is required, since some key <-> user_id records might be duplicated
        // values will be recomputed by next run of the calculate_averages command
        $this->query("TRUNCATE TABLE `user_stats`");

        $this->table('user_stats')
            ->removeIndex(['key', 'value'])
            ->addIndex(['key', 'user_id'], ['unique' => true])
            ->update();
    }

    public function down()
    {
        $this->query("TRUNCATE TABLE `user_stats`");

        $this->table('user_stats')
            ->removeIndex(['key', 'user_id'])
            ->addIndex(['key', 'value'], ['unique' => true])
            ->update();
    }
}
