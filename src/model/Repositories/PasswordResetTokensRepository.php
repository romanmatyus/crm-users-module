<?php

namespace Crm\UsersModule\Repository;

use Crm\ApplicationModule\Repository;
use Nette\Database\Table\IRow;
use Nette\Utils\DateTime;
use Ramsey\Uuid\Uuid;

class PasswordResetTokensRepository extends Repository
{
    protected $tableName = 'password_reset_tokens';

    public function add(IRow $user, $expire = '+5 hours')
    {
        return $this->insert([
            'user_id' => $user->id,
            'token' => sha1(Uuid::uuid4()),
            'created_at' => new DateTime(),
            'expire_at' => DateTime::from(strtotime($expire)),
        ]);
    }

    public function loadAvailableToken($token)
    {
        return $this->getTable()->where([
            'token' => $token,
            'expire_at > ?' => new DateTime(),
            'used_at' => null,
        ])->fetch();
    }

    public function isAvailable($token)
    {
        if (!$token) {
            return false;
        }

        return $this->getTable()->where([
            'token' => $token,
            'expire_at > ?' => new DateTime(),
            'used_at' => null,
        ])->count('*') > 0;
    }

    public function markUsed($token)
    {
        return $this->getTable()->where(['token' => $token])->update(['used_at' => new DateTime()]);
    }

    public function userTokens($userId)
    {
        return $this->getTable()->where(['user_id' => $userId])->order('created_at DESC');
    }
}
