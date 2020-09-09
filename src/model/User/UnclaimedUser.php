<?php

namespace Crm\UsersModule\User;

use Crm\UsersModule\Auth\InvalidEmailException;
use Crm\UsersModule\Auth\UserManager;
use Crm\UsersModule\Repository\UserAlreadyExistsException;
use Crm\UsersModule\Repository\UserMetaRepository;
use Exception;
use Nette\Database\Table\ActiveRow;
use Nette\Utils\JsonException;
use Ramsey\Uuid\Uuid;

class UnclaimedUser
{
    const META_KEY = 'unclaimed_user';

    private $userManager;

    private $userMetaRepository;

    public function __construct(
        UserManager $userManager,
        UserMetaRepository $userMetaRepository
    ) {
        $this->userManager = $userManager;
        $this->userMetaRepository = $userMetaRepository;
    }

    /**
     * @param string|null $email
     * @param string $source
     * @return ActiveRow
     * @throws InvalidEmailException
     * @throws JsonException
     * @throws UserAlreadyExistsException
     * @throws Exception
     */
    public function createUnclaimedUser(string $email = null, $source = 'unknown'): ActiveRow
    {
        if ($email === null) {
            $email = $this->generateUnclaimedUserEmail();
        }

        $user = $this->userManager->addNewUser($email, false, $source, null, false, null, false);
        $this->userMetaRepository->add($user, self::META_KEY, 1);
        return $user;
    }

    /**
     * @param ActiveRow $user
     * @return bool
     */
    public function isUnclaimedUser(ActiveRow $user): bool
    {
        $meta = $this->userMetaRepository->userMetaValueByKey($user, self::META_KEY);
        if ($meta) {
            return true;
        }
        return false;
    }

    /**
     * @return string
     * @throws Exception
     */
    public function generateUnclaimedUserEmail(): string
    {
        $uuid = Uuid::uuid4()->toString();
        return $uuid . '@unclaimed';
    }
}
