<?php

namespace Crm\UsersModule\Repository;

use Crm\ApplicationModule\DataProvider\DataProviderManager;
use Crm\ApplicationModule\Repository;
use Crm\ApplicationModule\Request;
use Crm\UsersModule\Auth\Access\TokenGenerator;
use Crm\UsersModule\DataProvider\AccessTokenDataProviderInterface;
use Crm\UsersModule\Events\BeforeRemoveAccessTokenEvent;
use Crm\UsersModule\Events\NewAccessTokenEvent;
use Crm\UsersModule\Events\PairDeviceAccessTokensEvent;
use Crm\UsersModule\Events\RemovedAccessTokenEvent;
use Crm\UsersModule\Events\UnpairDeviceAccessTokensEvent;
use Crm\UsersModule\User\UnclaimedUser;
use DateTime;
use League\Event\Emitter;
use Nette\Database\Explorer;
use Nette\Database\Table\ActiveRow;

class AccessTokensRepository extends Repository
{
    protected $tableName = 'access_tokens';

    private $emitter;

    private $userMetaRepository;

    private $dataProviderManager;

    public function __construct(
        Explorer $database,
        Emitter $emitter,
        UserMetaRepository $userMetaRepository,
        DataProviderManager $dataProviderManager
    ) {
        parent::__construct($database);
        $this->database = $database;
        $this->emitter = $emitter;
        $this->userMetaRepository = $userMetaRepository;
        $this->dataProviderManager = $dataProviderManager;
    }

    final public function all($limit = 500)
    {
        return $this->getTable()->order('created_at DESC')->limit($limit);
    }

    final public function add(ActiveRow $user, int $version = 3, ?string $source = null)
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
            'source' => $source,
        ]);

        $this->emitter->emit(new NewAccessTokenEvent($user->id, $token));
        return $row;
    }

    final public function remove($token)
    {
        $tokenRow = $this->loadToken($token);
        if (!$tokenRow) {
            return true;
        }
        $this->emitter->emit(new BeforeRemoveAccessTokenEvent($tokenRow));
        $result = $this->delete($tokenRow);
        $this->emitter->emit(new RemovedAccessTokenEvent($tokenRow->user_id, $token, $tokenRow->source));
        return $result;
    }

    final public function loadToken($token)
    {
        return $this->getTable()->where(['token' => $token])->fetch();
    }

    final public function allUserTokens($userId)
    {
        return $this->getTable()->where(['user_id' => $userId])->order('created_at DESC');
    }

    final public function allUserTokensBySource($userId, ?string $source)
    {
        return $this->allUserTokens($userId)->where(['source' => $source]);
    }

    final public function findAllByDeviceToken(ActiveRow $deviceToken)
    {
        return $this->getTable()->where('device_token_id', $deviceToken->id)->fetchAll();
    }

    final public function pairWithDeviceToken($accessToken, $deviceToken)
    {
        if (!$this->userMetaRepository->userMetaValueByKey($accessToken->user, UnclaimedUser::META_KEY)) {
            $this->unpairDeviceToken($deviceToken);
        }

        $this->update($accessToken, [
            'device_token_id' => $deviceToken->id
        ]);
        $this->emitter->emit(new PairDeviceAccessTokensEvent($deviceToken, $accessToken));
    }

    final public function unpairDeviceToken($deviceToken)
    {
        $accessTokens = $this->findAllByDeviceToken($deviceToken);

        foreach ($accessTokens as $accessToken) {
            /** @var AccessTokenDataProviderInterface[] $accessTokenDataProviders */
            $accessTokenDataProviders = $this->dataProviderManager->getProviders(
                'users.dataprovider.access_tokens',
                AccessTokenDataProviderInterface::class
            );
            foreach ($accessTokenDataProviders as $provider) {
                if (!$provider->canUnpairDeviceToken($accessToken, $deviceToken)) {
                    continue 2;
                }
            }

            $isUserUnclaimed = $this->userMetaRepository->userMetaValueByKey($accessToken->user, UnclaimedUser::META_KEY);
            if ($isUserUnclaimed) {
                continue;
            }

            $this->emitter->emit(new UnpairDeviceAccessTokensEvent($deviceToken, $accessToken));
            $this->update($accessToken, ['device_token_id' => null]);
        }
    }

    final public function removeAllUserTokens($userId, array $exceptTokens = [])
    {
        $tokens = $this->getTable()->where(['user_id' => $userId]);

        if ($exceptTokens) {
            $tokens->where('token NOT IN (?)', $exceptTokens);
        }

        $removed = 0;
        foreach ($tokens as $token) {
            $this->remove($token->token);
            $removed++;
        }

        return $removed;
    }

    final public function removeNotUsedTokens(DateTime $usedBefore)
    {
        $tokens = $this->getTable()->where('last_used_at < ', $usedBefore);
        $removed = 0;
        foreach ($tokens as $token) {
            $this->remove($token->token);
            $removed++;
        }
        return $removed;
    }

    final public function getVersionStats()
    {
        $result = [];
        $stats = $this->getTable()->select('COUNT(*) AS counts, version')->group('version');
        foreach ($stats as $stat) {
            $result[$stat->version] = $stat->counts;
        }
        return $result;
    }

    final public function existsForUserDeviceToken(ActiveRow $user, ActiveRow $deviceToken) : bool
    {
        return $this->getTable()->where([
            'device_token_id' => $deviceToken->id,
            'user_id' => $user->id
        ])->count('*') > 0;
    }
}
