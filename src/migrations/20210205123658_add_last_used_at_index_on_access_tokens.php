<?php

use Phinx\Migration\AbstractMigration;

class AddLastUsedAtIndexOnAccessTokens extends AbstractMigration
{
    public function up()
    {
        $this->table('access_tokens')
            ->addIndex('last_used_at')
            ->update();

    }

    public function down()
    {
        $this->table('access_tokens')
            ->removeIndex('last_used_at')
            ->update();
    }
}
