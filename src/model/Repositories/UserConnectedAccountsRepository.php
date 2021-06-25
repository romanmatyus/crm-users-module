<?php

namespace Crm\UsersModule\Repository;

use Crm\ApplicationModule\Repository;
use Crm\ApplicationModule\Repository\AuditLogRepository;
use Nette\Database\Context;
use Nette\Database\Table\IRow;
use Nette\Utils\Json;

class UserConnectedAccountsRepository extends Repository
{
    public const TYPE_APPLE_SIGN_IN = 'apple_sign_in';

    public const TYPE_GOOGLE_SIGN_IN = 'google_sign_in';

    protected $tableName = 'user_connected_accounts';

    public function __construct(
        Context $database,
        AuditLogRepository $auditLogRepository
    ) {
        parent::__construct($database);
        $this->database = $database;
        $this->auditLogRepository = $auditLogRepository;
    }

    final public function add(
        IRow $user,
        string $type,
        string $externalId,
        ?string $email,
        $meta = null
    ) {
        return $this->insert([
            'user_id' => $user->id,
            'external_id' => $externalId,
            'email' => $email,
            'type' => $type,
            'created_at' => new \DateTime(),
            'updated_at' => new \DateTime(),
            'meta' => $meta ? Json::encode($meta) : null,
        ]);
    }

    final public function getByExternalId(string $type, string $externalId)
    {
        return $this->getTable()->where([
            'external_id' => $externalId,
            'type' => $type,
        ])->fetch();
    }

    final public function getForUser(IRow $user, string $type)
    {
        return $this->getTable()->where([
            'user_id' => $user->id,
            'type' => $type,
        ])->fetch();
    }

    public function removeAccountsForUser(IRow $user): int
    {
        return $this->getTable()
            ->where(['user_id' => $user->id])
            ->delete();
    }
}
