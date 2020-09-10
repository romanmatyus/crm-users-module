<?php

namespace Crm\UsersModule\DataProvider;

use Crm\ApplicationModule\DataProvider\DataProviderException;
use Crm\UsersModule\Repository\UserMetaRepository;
use Crm\UsersModule\Repository\UsersRepository;
use Crm\UsersModule\User\ClaimUserDataProviderInterface;
use Crm\UsersModule\User\UnclaimedUser;

class UsersClaimUserDataProvider implements ClaimUserDataProviderInterface
{
    private $usersRepository;

    private $userMetaRepository;

    public function __construct(
        UsersRepository $usersRepository,
        UserMetaRepository $userMetaRepository
    ) {
        $this->userMetaRepository = $userMetaRepository;
        $this->usersRepository = $usersRepository;
    }

    public function provide(array $params): void
    {
        if (!isset($params['unclaimedUser'])) {
            throw new DataProviderException('unclaimedUser param missing');
        }
        if (!isset($params['loggedUser'])) {
            throw new DataProviderException('loggedUser param missing');
        }

        $unclaimedUserMetas = $this->userMetaRepository->userMetaRows($params['unclaimedUser'])->fetchAll();
        foreach ($unclaimedUserMetas as $unclaimedUserMeta) {
            if ($unclaimedUserMeta->key === UnclaimedUser::META_KEY) {
                continue;
            }
            $this->userMetaRepository->update($unclaimedUserMeta, ['user_id' => $params['loggedUser']->id]);
        }

        // trim - if any of the notes is null or empty
        $mergedNote = trim($params['loggedUser']->note . "\n" . $params['unclaimedUser']->note);
        $mergedNote = empty($mergedNote) ? null : $mergedNote;

        $this->usersRepository->update($params['loggedUser'], ['note' => $mergedNote]);
    }
}
