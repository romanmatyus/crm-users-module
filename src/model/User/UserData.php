<?php

namespace Crm\UsersModule\User;

use Crm\ApplicationModule\User\UserDataRegistrator;
use Crm\ApplicationModule\User\UserDataStorageInterface;
use Crm\UsersModule\Repository\AccessTokensRepository;
use Nette\Utils\Json;

class UserData
{
    private $userDataRegistrator;

    private $userDataStorage;

    private $accessTokensRepository;

    public function __construct(
        UserDataRegistrator $userDataRegistrator,
        UserDataStorageInterface $userDataStorage,
        AccessTokensRepository $accessTokensRepository
    ) {
        $this->userDataRegistrator = $userDataRegistrator;
        $this->userDataStorage = $userDataStorage;
        $this->accessTokensRepository = $accessTokensRepository;
    }

    public function refreshUserTokens($userId)
    {
        $userDataContent = $this->userDataRegistrator->generate($userId);
        $tokens = $this->accessTokensRepository->allUserTokens($userId);

        $tokensString = [];
        foreach ($tokens as $token) {
            $tokensString[] = $token->token;
        }
        $this->userDataStorage->multiStore($tokensString, Json::encode($userDataContent));
    }

    public function getUserToken($token)
    {
        $data = $this->userDataStorage->load($token);
        if ($data) {
            return Json::decode($data);
        }
        return false;
    }

    public function getUserTokens(array $tokens)
    {
        $data = $this->userDataStorage->multiLoad($tokens);
        $result = [];
        foreach ($data as $row) {
            if ($row !== null) {
                $result[] = Json::decode($row);
            }
        }
        return $result;
    }

    public function removeUserToken($token)
    {
        return $this->userDataStorage->remove($token);
    }
}
