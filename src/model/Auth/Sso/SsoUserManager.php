<?php


namespace Crm\UsersModule\Auth\Sso;

use Crm\UsersModule\Auth\PasswordGenerator;
use Crm\UsersModule\Builder\UserBuilder;
use Crm\UsersModule\Repository\UserConnectedAccountsRepository;
use Crm\UsersModule\Repository\UsersRepository;
use Nette\Database\Context;
use Nette\Database\Table\IRow;

class SsoUserManager
{
    private $dbContext;

    private $passwordGenerator;

    private $userBuilder;

    private $connectedAccountsRepository;

    private $usersRepository;

    public function __construct(
        Context $dbContext,
        PasswordGenerator $passwordGenerator,
        UserBuilder $userBuilder,
        UserConnectedAccountsRepository $connectedAccountsRepository,
        UsersRepository $usersRepository
    ) {
        $this->dbContext = $dbContext;
        $this->passwordGenerator = $passwordGenerator;
        $this->userBuilder = $userBuilder;
        $this->connectedAccountsRepository = $connectedAccountsRepository;
        $this->usersRepository = $usersRepository;
    }

    public function getUser(string $externalId, string $email, string $type, string $source, $meta = null, $loggedUserId = null): IRow
    {
        $this->dbContext->beginTransaction();
        try {
            if ($loggedUserId) {
                $connectedAccount = $this->connectedAccountsRepository->getByExternalId($type, $externalId);
                if ($connectedAccount && $connectedAccount->user->id !== $loggedUserId) {
                    throw new AlreadyLinkedAccountSsoException($externalId, $email);
                }

                $user = $this->usersRepository->find($loggedUserId);
            } else {
                $user = $this->matchSsoUser(
                    $type,
                    $externalId,
                    $email
                );

                if (!$user) {
                    // if user is not in our DB, create him/her
                    // our access_token is not automatically created
                    $password = $this->passwordGenerator->generatePassword();
                    $user = $this->userBuilder->createNew()
                        ->setEmail($email)
                        ->setPassword($password)
                        ->setPublicName($email)
                        ->setRole('user')
                        ->setActive(true)
                        ->setIsInstitution(false)
                        ->setSource($source)
                        ->setAddTokenOption(false)
                        ->save();
                }
            }

            $connectedAccount = $this->connectedAccountsRepository->getForUser($user, $type);
            if (!$connectedAccount) {
                $this->connectedAccountsRepository->add(
                    $user,
                    $type,
                    $externalId,
                    $email,
                    $meta
                );
            }
        } catch (\Exception $e) {
            $this->dbContext->rollBack();
            throw $e;
        }
        $this->dbContext->commit();

        return $user;
    }

    public function matchSsoUser(string $connectedAccountType, string $externalId, string $email): ?IRow
    {
        // external ID has priority over email
        $connectedAccount = $this->connectedAccountsRepository->getByExternalId($connectedAccountType, $externalId);
        if ($connectedAccount) {
            return $connectedAccount->user;
        }

        return $this->usersRepository->getByEmail($email) ?: null;
    }
}
