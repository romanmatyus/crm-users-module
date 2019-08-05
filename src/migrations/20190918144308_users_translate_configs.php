<?php

use Phinx\Migration\AbstractMigration;

class UsersTranslateConfigs extends AbstractMigration
{
    public function up()
    {
        $this->execute("
            update configs set display_name = 'users.config.not_logged_in_route.name' where name = 'not_logged_in_route';
            update configs set description = 'users.config.not_logged_in_route.description' where name = 'not_logged_in_route';
        ");
    }

    public function down()
    {

    }
}
