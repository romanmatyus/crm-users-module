<?php

use Phinx\Migration\AbstractMigration;

class AddExternalIdToConnectedAccountsTable extends AbstractMigration
{
    public function up()
    {
        $this->table('user_connected_accounts')
            ->removeIndex(['type', 'email'])
            ->update();

        $this->table('user_connected_accounts')
            ->addColumn('external_id', 'string', ['after' => 'type', 'null' => true])
            ->update();

        // Currently, all connected accounts are Google Sign-In accounts, which SHOULD HAVE stored 'sub' or 'id' (equivalent) in the meta field.
        // However, we use email as a backup so external_id column is never empty (but this SHOULD NOT happen)
        $this->execute(<<<SQL
            UPDATE `user_connected_accounts`
            SET `external_id` = COALESCE(JSON_UNQUOTE(JSON_EXTRACT(meta, '$.sub')), JSON_UNQUOTE(JSON_EXTRACT(meta, '$.id')), email);
SQL);

        $this->table('user_connected_accounts')
            ->changeColumn('external_id', 'string', ['null' => false])
            ->update();

        $this->table('user_connected_accounts')
            // user might have max ONE connected account of a given type
            ->addIndex(['user_id', 'type'], ['unique' => true])
            // connected account of a given type and ID can be assigned only once
            ->addIndex(['type', 'external_id'], ['unique' => true])
            ->update();

        // Some connected accounts might not even have email, therefore making it nullable
        $this->table('user_connected_accounts')
            ->changeColumn('email', 'string', ['null' => true])
            ->update();
    }

    public function down()
    {
        $this->output->writeln('<error>Data rollback is risky. See migration class for details. Nothing done.</error>');
        // remove return if you are 100% sure you know what you are doing
        return;

        $this->table('user_connected_accounts')
            ->removeIndex(['user_id', 'type', 'external_id'])
            ->update();

        $this->table('user_connected_accounts')
            ->removeColumn('external_id')
            ->update();

        $this->table('user_connected_accounts')
            ->addIndex(['type', 'email'], ['unique' => true])
            ->update();

        $this->table('user_connected_accounts')
            ->changeColumn('email', 'string', ['null' => false])
            ->update();
    }
}
