<?php

use Phinx\Migration\AbstractMigration;

class AddEmailValidatedAtFlag extends AbstractMigration
{
    public function change()
    {
        $this->table('users')
             ->addColumn('email_validated_at', 'datetime', [ 'null' => true, 'after' => 'confirmed_at' ])
             ->update();
    
        // use current "confirmed_at" values as default values
        // any new user should get a null as default
        $this->execute('UPDATE users SET email_validated_at = confirmed_at');
    }
}
