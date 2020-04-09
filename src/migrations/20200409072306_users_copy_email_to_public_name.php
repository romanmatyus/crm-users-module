<?php

use Phinx\Migration\AbstractMigration;

class UsersCopyEmailToPublicName extends AbstractMigration
{
    public function up()
    {
        $sql = <<<SQL
UPDATE users
SET public_name = email
WHERE public_name IS NULL OR public_name = '';
SQL;

        $this->execute($sql);

        $this->table('users')
            ->changeColumn('public_name', 'string', ['null' => false])
            ->update();
    }

    public function down()
    {
        $this->output->writeln('Down migration for data is not available. Changing column back to nullable.');

        $this->table('users')
            ->changeColumn('public_name', 'string', ['null' => true])
            ->update();
    }
}
