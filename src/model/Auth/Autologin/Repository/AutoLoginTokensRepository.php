<?php

namespace Crm\UsersModule\Auth\AutoLogin\Repository;

use Crm\ApplicationModule\Repository;
use Crm\ApplicationModule\Repository\RetentionData;
use Nette\Database\IRow;

class AutoLoginTokensRepository extends Repository
{
    protected $tableName = 'autologin_tokens';

    use RetentionData;

    public function add($token, IRow $user, $validFrom, $validTo, $maxCount = 1)
    {
        return $this->insert([
            'token' => $token,
            'user_id' => $user->id,
            'email' => $user->email,
            'valid_from' => $validFrom,
            'valid_to' => $validTo,
            'max_count' => $maxCount,
            'used_count' => 0,
            'created_at' => new \DateTime(),
        ]);
    }

    public function userTokens($userId)
    {
        return $this->getTable()->where('user_id', $userId)->order('valid_to DESC');
    }

    protected function removingField()
    {
        return 'valid_to';
    }

    public function deleteAll($userId)
    {
        return $this->getTable()->where('user_id', $userId)->delete();
    }
}
