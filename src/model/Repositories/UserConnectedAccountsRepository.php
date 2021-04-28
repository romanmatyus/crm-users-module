<?php

namespace Crm\UsersModule\Repository;

use Crm\ApplicationModule\Repository;
use Crm\ApplicationModule\Repository\AuditLogRepository;
use Nette\Database\Context;
use Nette\Database\Table\IRow;
use Nette\Utils\Json;

class UserConnectedAccountsRepository extends Repository
{
    const TYPE_GOOGLE_SIGN_IN = 'google_sign_in';

    protected $tableName = 'user_connected_accounts';

    private $usersRepository;

    public function __construct(
        Context $database,
        UsersRepository $usersRepository,
        AuditLogRepository $auditLogRepository
    ) {
        parent::__construct($database);
        $this->database = $database;
        $this->usersRepository = $usersRepository;
        $this->auditLogRepository = $auditLogRepository;
    }

    final public function add(
        IRow $user,
        string $type,
        string $email,
        $meta = null
    ) {
        return $this->insert([
            'user_id' => $user->id,
            'email' => $email,
            'type' => $type,
            'created_at' => new \DateTime(),
            'updated_at' => new \DateTime(),
            'meta' => $meta ? Json::encode($meta) : null,
        ]);
    }

    /**
     * Loads either user with given $email (user has preference over connected account)
     * or user having connected account with given $email and $type.
     *
     * @param string $email
     * @param string $type
     *
     * @return IRow|null
     */
    final public function getUserByEmail(string $email, string $type): ?IRow
    {
        $user = $this->usersRepository->getByEmail($email);

        if ($user) {
            return $user;
        }

        $row = $this->getTable()->where([
            'email' => $email,
            'type' => $type
        ])->fetch();

        if ($row) {
            return $row->user;
        }

        return null;
    }

    final public function getForUser(IRow $user, string $type)
    {
        return $this->getTable()->where([
            'user_id' => $user->id,
            'type' => $type,
        ])->fetch();
    }
}
