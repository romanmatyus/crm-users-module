<?php

namespace Crm\UsersModule\Repository;

use Crm\ApplicationModule\Repository;
use Crm\UsersModule\Auth\Access\TokenGenerator;
use Nette\Utils\DateTime;

class UserEmailConfirmationsRepository extends Repository
{
    protected $tableName = 'user_email_confirmations';

    public function generate(int $userId)
    {
        return $this->insert([
            'user_id' => $userId,
            'token' => TokenGenerator::generate(32),
        ]);
    }

    public function verify(string $token): bool
    {
        $emailConfirmationRow = $this->getTable()->where('token', $token)->fetch();
        if (!$emailConfirmationRow) {
            return false;
        }

        if ($emailConfirmationRow->confirmed_at === null) {
            return $this->update($emailConfirmationRow, ['confirmed_at' => new DateTime()]);
        }

        return true;
    }
}
