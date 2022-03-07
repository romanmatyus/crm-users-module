<?php

use Phinx\Migration\AbstractMigration;

class RemoveRedundantRegistrationAttemptsColumns extends AbstractMigration
{

    public function up()
    {
        $this->table('registration_attempts')
            ->changeColumn('user_agent', 'text', ['null' => true])
            ->removeColumn('os')
            ->removeColumn('device')
            ->update();
    }

    public function down()
    {
        $this->output->writeln('Down migration is not available. Removed columns were not intended to be created in the first place.');
    }
}
