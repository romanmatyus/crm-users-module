<?php

namespace Crm\UsersModule\Locale;

use Contributte\Translation\LocalesResolvers\ResolverInterface;
use Contributte\Translation\Translator;
use Nette\Security\IUserStorage;
use Nette\Security\SimpleIdentity;

class UserDataLocaleResolver implements ResolverInterface
{
    private IUserStorage $userStorage;

    public function __construct(IUserStorage $userStorage)
    {
        $this->userStorage = $userStorage;
    }

    public function resolve(Translator $translator): ?string
    {
        if ($this->userStorage->isAuthenticated()) {
            $identity = $this->userStorage->getIdentity();
            if ($identity && $identity instanceof SimpleIdentity) {
                $data = $identity->getData();
                return $data['locale'] ?? null;
            }
        }

        return null;
    }
}
