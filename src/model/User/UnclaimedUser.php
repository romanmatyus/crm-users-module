<?php

namespace Crm\UsersModule\User;

use Crm\ApplicationModule\DataProvider\DataProviderManager;
use Crm\UsersModule\Auth\Access\AccessTokenNotFoundException;
use Crm\UsersModule\Auth\InvalidEmailException;
use Crm\UsersModule\Auth\UserManager;
use Crm\UsersModule\Events\UserClaimedEvent;
use Crm\UsersModule\Repository\AccessTokensRepository;
use Crm\UsersModule\Repository\UserAlreadyExistsException;
use Crm\UsersModule\Repository\UserMetaRepository;
use Crm\UsersModule\Repository\UsersRepository;
use Exception;
use League\Event\Emitter;
use Nette\Database\Context;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\IRow;
use Nette\Utils\JsonException;
use Ramsey\Uuid\Uuid;

class UnclaimedUser
{
    const META_KEY = 'unclaimed_user';

    const CLAIMED_BY_KEY = 'claimed_by';

    const CLAIMED_UNCLAIMED_USER_KEY = 'claimed_unclaimed_user';

    private $userManager;

    private $userMetaRepository;

    private $dataProviderManager;

    private $accessTokensRepository;

    private $emitter;

    private $usersRepository;

    private $userData;

    private Context $dbContext;

    public function __construct(
        AccessTokensRepository $accessTokensRepository,
        DataProviderManager $dataProviderManager,
        Emitter $emitter,
        UserManager $userManager,
        UserMetaRepository $userMetaRepository,
        UsersRepository $usersRepository,
        UserData $userData,
        Context $dbContext
    ) {
        $this->userManager = $userManager;
        $this->userMetaRepository = $userMetaRepository;
        $this->dataProviderManager = $dataProviderManager;
        $this->accessTokensRepository = $accessTokensRepository;
        $this->emitter = $emitter;
        $this->usersRepository = $usersRepository;
        $this->userData = $userData;
        $this->dbContext = $dbContext;
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

        return $this->userManager->addNewUser(
            $email,
            false,
            $source,
            null,
            false,
            null,
            false,
            [self::META_KEY => 1],
            false
        );
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

    public function claimUser(IRow $unclaimedUser, IRow $loggedUser, ?IRow $deviceToken = null): void
    {
        if (!$this->isUnclaimedUser($unclaimedUser)) {
            throw new ClaimedUserException("User {$unclaimedUser->id} is claimed");
        }
        if ($this->isUnclaimedUser($loggedUser)) {
            throw new UnclaimedUserException("User {$loggedUser->id} is unclaimed");
        }

        // Device token is not required, since unclaimed user may exist without any access token (e.g. user created via pub/sub notification)
        if ($deviceToken && !$this->accessTokensRepository->existsForUserDeviceToken($loggedUser, $deviceToken)) {
            throw new AccessTokenNotFoundException("There is no access token for user {$loggedUser->id} and device token {$deviceToken->id}");
        }

        $providers = $this->dataProviderManager->getProviders('users.dataprovider.claim_unclaimed_user', ClaimUserDataProviderInterface::class);
        foreach ($providers as $sorting => $provider) {
            $provider->provide(['unclaimedUser' => $unclaimedUser, 'loggedUser' => $loggedUser]);
        }

        // deactivate unclaimed user
        if ($unclaimedUser->active) {
            $this->usersRepository->toggleActivation($unclaimedUser);
        }

        $this->userMetaRepository->add($unclaimedUser, self::CLAIMED_BY_KEY, $loggedUser->id);
        $this->userMetaRepository->add($loggedUser, self::CLAIMED_UNCLAIMED_USER_KEY, $unclaimedUser->id);

        $this->userData->refreshUserTokens($loggedUser->id);
        $this->emitter->emit(new UserClaimedEvent($unclaimedUser, $loggedUser, $deviceToken));
    }

    /**
     * getClaimer returns user, who claimed provided unclaimed user. If unclaimed user was not claimed yet, method
     * returns null.
     */
    public function getClaimer($unclaimedUser)
    {
        $claimerUserId = $this->userMetaRepository->userMetaValueByKey($unclaimedUser, self::CLAIMED_BY_KEY);
        if (!$claimerUserId) {
            return null;
        }
        return $this->usersRepository->find($claimerUserId);
    }

    public function getPreviouslyClaimedUser($user)
    {
        $claimedUserId = $this->userMetaRepository->userMetaValueByKey($user, self::CLAIMED_UNCLAIMED_USER_KEY);
        if (!$claimedUserId) {
            return null;
        }
        return $this->usersRepository->find($claimedUserId);
    }

    public function makeUnclaimedUserRegistered(
        $unclaimedUser,
        $sendEmail = true,
        $source = 'unknown',
        $referer = null,
        $checkEmail = true,
        $password = null,
        $deviceToken = null
    ) {
        $this->dbContext->beginTransaction();
        try {
            $email = $unclaimedUser->email;
            $this->usersRepository->update($unclaimedUser, ['email' => $this->generateUnclaimedUserEmail()]);

            $user = $this->userManager->addNewUser($email, $sendEmail, $source, $referer, $checkEmail, $password);
            $this->claimUser($unclaimedUser, $user, $deviceToken);
        } catch (Exception $exception) {
            $this->dbContext->rollback();
            throw $exception;
        }

        $this->dbContext->commit();

        return $user;
    }
}
