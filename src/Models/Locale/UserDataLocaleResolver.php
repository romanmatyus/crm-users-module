<?php

namespace Crm\UsersModule\Locale;

use Kdyby\Translation\IUserLocaleResolver;
use Kdyby\Translation\Translator;
use Nette\Security\IUserStorage;
use Nette\Security\Identity;

class UserDataLocaleResolver implements IUserLocaleResolver
{
    private IUserStorage $userStorage;

    public function __construct(IUserStorage $userStorage)
    {
        $this->userStorage = $userStorage;
    }

    public function resolve(Translator $translator)
    {
        if ($this->userStorage->isAuthenticated()) {
            $identity = $this->userStorage->getIdentity();
            if ($identity && $identity instanceof Identity) {
                $data = $identity->getData();
                return $data['locale'] ?? null;
            }
        }

        return null;
    }
}
