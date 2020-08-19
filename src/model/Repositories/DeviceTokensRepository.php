<?php

namespace Crm\UsersModule\Repositories;

use Crm\ApplicationModule\Repository;
use Crm\UsersModule\Auth\Access\TokenGenerator;
use Nette\Utils\DateTime;

class DeviceTokensRepository extends Repository
{
    protected $tableName = 'device_tokens';

    final public function add(string $deviceId)
    {
        $token = TokenGenerator::generate();

        $row = $this->insert([
            'device_id' => $deviceId,
            'created_at' => new DateTime(),
            'last_used_at' => new DateTime(),
            'token' => $token,
        ]);

        return $row;
    }

    final public function findByToken(string $token)
    {
        return $this->getTable()->where('token', $token)->fetch();
    }

    final public function findByDeviceId(string $deviceId)
    {
        return $this->getTable()->where('device_id', $deviceId)->fetch();
    }
}
