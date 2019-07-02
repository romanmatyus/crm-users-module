<?php

namespace Crm\UsersModule\Repository;

use Crm\ApplicationModule\Repository;
use Crm\ApplicationModule\Request;
use Crm\UsersModule\Auth\Access\StorageInterface;
use Crm\UsersModule\Auth\Access\TokenGenerator;
use Crm\UsersModule\Events\NewAccessTokenEvent;
use Crm\UsersModule\Events\RemovedAccessTokenEvent;
use DateTime;
use League\Event\Emitter;
use Nette\Database\Context;
use Nette\Database\IRow;
use Tracy\Debugger;

class AccessTokensRepository extends Repository
{
    protected $tableName = 'access_tokens';

    private $backend;

    private $emitter;

    public function __construct(
        Context $database,
        StorageInterface $backend,
        Emitter $emitter
    ) {
        parent::__construct($database);
        $this->database = $database;
        $this->backend = $backend;
        $this->emitter = $emitter;
    }

    public function all($limit = 500)
    {
        return $this->getTable()->order('created_at DESC')->limit($limit);
    }

    public function add(IRow $user, int $version)
    {
        $token = TokenGenerator::generate();

        $row = $this->insert([
            'token' => $token,
            'created_at' => new DateTime(),
            'last_used_at' => new DateTime(),
            'user_id' => $user->id,
            'ip' => Request::getIp(),
            'user_agent' => Request::getUserAgent(),
            'version' => $version,
        ]);

        $addedCount = $this->backend->addToken($token, 'register');
        if ($addedCount === 0) {
            Debugger::log("Token [{$token}] of user [{$user->id}] was not added to Redis", Debugger::WARNING);
        }
        $this->emitter->emit(new NewAccessTokenEvent($user->id, $token));

        return $row;
    }

    public function remove($token)
    {
        $this->backend->removeToken($token);
        $this->backend->removeToken($token, 'register');
        $tokenRow = $this->loadToken($token);
        if (!$tokenRow) {
            return true;
        }
        $result = $this->delete($tokenRow);
        $this->emitter->emit(new RemovedAccessTokenEvent($tokenRow->user_id, $token));
        return $result;
    }

    public function loadToken($token)
    {
        return $this->getTable()->where(['token' => $token])->fetch();
    }

    public function validCacheToken($token, $type = 'access')
    {
        return $this->backend->tokenExists($token, $type);
    }

    public function allUserTokens($userId)
    {
        return $this->getTable()->where(['user_id' => $userId])->order('created_at DESC');
    }

    public function removeAllUserTokens($userId)
    {
        $tokens = $this->getTable()->where(['user_id' => $userId]);

        $removed = 0;
        foreach ($tokens as $token) {
            $this->remove($token->token);
            $removed++;
        }

        return $removed;
    }

    public function grantUserAccess(IRow $user)
    {
        $tokens = $this->allUserTokens($user->id);
        foreach ($tokens as $token) {
            $this->backend->addToken($token->token);
        }
    }

    public function denyUserAccess(IRow $user)
    {
        $tokens = $this->allUserTokens($user->id);
        foreach ($tokens as $token) {
            $this->backend->removeToken($token->token);
        }
    }

    public function removeNotUsedTokens(DateTime $usedBefore)
    {
        $tokens = $this->getTable()->where('last_used_at < ', $usedBefore);
        $removed = 0;
        foreach ($tokens as $token) {
            $this->remove($token->token);
            $removed++;
        }
        return $removed;
    }

    public function getVersionStats()
    {
        $result = [];
        $stats = $this->getTable()->select('COUNT(*) AS pocet, version')->group('version');
        foreach ($stats as $stat) {
            $result[$stat->version] = $stat->pocet;
        }
        return $result;
    }
}
