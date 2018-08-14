<?php

namespace Crm\UsersModule\Auth\AutoLogin;

use Crm\UsersModule\Auth\AutoLogin\Repository\AutoLoginTokensRepository;
use Nette\Database\IRow;
use Nette\Utils\DateTime;

class AutoLogin
{
    /** @var AutoLoginTokensRepository */
    private $autoLoginTokensRepository;

    public function __construct(AutoLoginTokensRepository $autoLoginTokensRepository)
    {
        $this->autoLoginTokensRepository = $autoLoginTokensRepository;
    }

    public function getToken($token)
    {
        return $this->autoLoginTokensRepository->findBy('token', $token);
    }

    public function getValidToken($token)
    {
        return $this->autoLoginTokensRepository->getTable()->where([
            'token' => $token,
            'valid_from <' => new DateTime(),
            'valid_to >' => new DateTime(),
            'used_count < max_count'
        ])->fetch();
    }

    public function incrementTokenUse(IRow $token)
    {
        return $this->autoLoginTokensRepository->update($token, ['used_count+=' => 1]);
    }

    public function addUserToken(IRow $user, DateTime $validFrom, DateTime $validTo, $maxCount = 1)
    {
        $token = $this->generateToken($user);
        return $this->autoLoginTokensRepository->add(
            $token,
            $user,
            $validFrom,
            $validTo,
            $maxCount
        );
    }

    private function generateToken(IRow $user)
    {
        return md5(time() . $user->id . $user->email . rand(100, 100000) . rand(10000, 1000000));
    }
}
