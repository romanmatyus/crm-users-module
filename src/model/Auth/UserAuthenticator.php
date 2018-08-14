<?php

namespace Crm\UsersModule\Auth;

use Crm\ApplicationModule\Authenticator\AuthenticatorInterface;
use Crm\ApplicationModule\Authenticator\AuthenticatorManager;
use Crm\UsersModule\Events\UserSignInEvent;
use Crm\UsersModule\Repository\UsersRepository;
use League\Event\Emitter;
use Nette\Security\AuthenticationException;
use Nette\Security\IAuthenticator;
use Nette\Security\Identity;
use Nette\SmartObject;

class UserAuthenticator implements IAuthenticator
{
    use SmartObject;

    const COLUMN_PASSWORD_HASH = 'password';

    private $emitter;

    private $authenticatorManager;

    public function __construct(
        Emitter $emitter,
        AuthenticatorManager $authenticatorManager
    ) {
        $this->emitter = $emitter;
        $this->authenticatorManager = $authenticatorManager;
    }

    /**
     * Performs an authentication.
     *
     * @return Identity
     * @throws AuthenticationException
     */
    public function authenticate(array $credentials)
    {
        // Dirty hack so we can use in both User->Authenticator->authenticate() and \Nette\Security\User->login() methods
        // arrays with named keys instead of anonymous arrays.
        // Eg. $userAuthenticator->authenticate(['username' => $username, 'alwaysLogin' => true]); instead of
        // $userAuthenticator->authenticate([$username, null, null, null, true]);
        if (count($credentials) == 1 && isset($credentials[0]) && is_array($credentials[0])) {
            $credentials = $credentials[0];
        }

        $user = false;
        $source = null;
        $exception = null;
        $authenticators = $this->authenticatorManager->getAuthenticators();
        /** @var AuthenticatorInterface $authenticator */
        foreach ($authenticators as $authenticator) {
            try {
                $u = $authenticator->setCredentials($credentials)->authenticate();
                if ($u !== null && $u !== false) {
                    $user = $u;
                    $source = $authenticator->getSource();
                    break;
                }
            } catch (AuthenticationException $e) {
                if ($exception === null) {
                    $exception = $e;
                }
                continue;
            }
        }

        if ($user === false && $exception !== null) {
            throw $exception;
        }
        if ($user === false) {
            throw new AuthenticationException('Nepodarilo sa overiť užívateľa.', UserAuthenticator::IDENTITY_NOT_FOUND);
        }
        $this->emitter->emit(new UserSignInEvent($user, $source));

        $arr = $user->toArray();
        unset($arr[self::COLUMN_PASSWORD_HASH]);

        $groups = [];
        if ($user['role'] === UsersRepository::ROLE_ADMIN) {
            $userGroups = $user->related('admin_user_groups');
            foreach ($userGroups as $userGroup) {
                $groups[] = $userGroup->admin_group->name;
            }
        }

        return new Identity($user['id'], $groups, $arr);
    }
}
