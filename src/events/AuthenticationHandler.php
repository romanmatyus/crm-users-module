<?php

namespace Crm\UsersModule\Events;

use Crm\ApplicationModule\Events\AuthenticationEvent;
use Crm\UsersModule\Auth\Access\AccessToken;
use Crm\UsersModule\Repository\AccessTokensRepository;
use Crm\UsersModule\Repository\UsersRepository;
use League\Event\AbstractListener;
use League\Event\EventInterface;
use Nette\Localization\ITranslator;
use Nette\Security\AuthenticationException;

class AuthenticationHandler extends AbstractListener
{
    private $usersRepository;

    private $accessToken;

    private $accessTokensRepository;

    private $translator;

    public function __construct(
        UsersRepository $usersRepository,
        AccessToken $accessToken,
        AccessTokensRepository $accessTokensRepository,
        ITranslator $translator
    ) {
        $this->usersRepository = $usersRepository;
        $this->accessToken = $accessToken;
        $this->accessTokensRepository = $accessTokensRepository;
        $this->translator = $translator;
    }

    public function handle(EventInterface $event)
    {
        if (!$event instanceof AuthenticationEvent) {
            throw new \Exception('invalid type of event received: ' . get_class($event));
        }

        $user = $this->usersRepository->find($event->getUserId());
        if (!$user->active) {
            throw new AuthenticationException($this->translator->translate('users.authenticator.inactive_account'));
        }

        $token = $this->accessToken->getToken($event->getRequest());
        if ($token && !$this->accessTokensRepository->loadToken($token)) {
            throw new AuthenticationException($this->translator->translate('users.frontend.sign_in.signed_out'));
        }
    }
}
