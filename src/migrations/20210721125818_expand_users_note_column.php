<?php

use Phinx\Migration\AbstractMigration;
use Phinx\Db\Adapter\MysqlAdapter;

class ExpandUsersNoteColumn extends AbstractMigration
{
    public function up()
    {
        $this->table('users')
            ->changeColumn('note', 'text', ['null' => true, 'limit' => MysqlAdapter::TEXT_REGULAR])
            ->update();
    }

    public function down()
    {
        $this->output->writeln('<error>Data rollback is risky. See migration class for details. Nothing done.</error>');
        // remove return if you are 100% sure you know what you are doing
        return;

        // ensure we have only 255 chars long notes
        $this->execute(<<<SQL
            UPDATE `users`
            SET `note` = SUBSTR(`note`, 1, 255)
            WHERE CHAR_LENGTH(`note`) > 255;
SQL);

        // update column size back to VARCHAR(255)
        $this->table('users')
            ->changeColumn('note', 'string', ['limit' => 255, 'null' => true])
            ->update();
    }
}
