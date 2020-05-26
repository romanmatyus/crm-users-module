<?php

use Phinx\Migration\AbstractMigration;

class FixEmptyPublicName extends AbstractMigration
{
    public function up()
    {
        $sql = <<<SQL
UPDATE users
SET public_name = email
WHERE public_name IS NULL OR public_name = '';
SQL;

        $this->execute($sql);
    }

    public function down()
    {
        $this->output->writeln('Down migration for data is not available.');
    }
}
