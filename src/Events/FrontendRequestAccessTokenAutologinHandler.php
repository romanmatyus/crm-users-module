<?php

namespace Crm\UsersModule\Events;

use Crm\ApplicationModule\Events\FrontendRequestEvent;
use Crm\UsersModule\Authenticator\AccessTokenAuthenticator;
use League\Event\AbstractListener;
use League\Event\EventInterface;
use Nette\Http\Request;
use Nette\Http\Response;
use Nette\Http\Session;
use Nette\Localization\Translator;
use Nette\Security\AuthenticationException;
use Nette\Security\Authenticator;
use Nette\Security\User;

class FrontendRequestAccessTokenAutologinHandler extends AbstractListener
{
    private Session $session;
    private Translator $translator;
    private User $user;
    private Request $httpRequest;
    private Response $httpResponse;

    public function __construct(
        Session $session,
        Translator $translator,
        User $user,
        Request $httpRequest,
        Response $httpResponse
    ) {
        $this->session = $session;
        $this->translator = $translator;
        $this->user = $user;
        $this->httpRequest = $httpRequest;
        $this->httpResponse = $httpResponse;
    }

    public function handle(EventInterface $event)
    {
        if (!$event instanceof FrontendRequestEvent) {
            throw new \Exception('Invalid type of event received, ' . self::class . ' expected: ' . get_class($event));
        }
        if ($this->user->isLoggedIn()) {
            return;
        }
        $accessToken = $this->httpRequest->getCookie('n_token');
        if (!$accessToken) {
            return;
        }
        $authSession = $this->session->getSection('auth');
        if ($authSession->get(AccessTokenAuthenticator::SESSION_AUTH_DISABLED)) {
            $event->addFlashMessages(
                $this->translator->translate('users.authenticator.access_token.autologin_disabled'),
                'notice'
            );
            return;
        }

        try {
            $this->user->login(['accessToken' => $accessToken]);
            // Do not refresh POST/PUT requests (otherwise data will get lost)
            if (!in_array($this->httpRequest->getMethod(), ['POST', 'PUT']) && $this->user->isLoggedIn()) {
                $this->httpResponse->addHeader('Refresh', 0);
                exit;
            }
        } catch (AuthenticationException $e) {
            if ($e->getCode() === Authenticator::NOT_APPROVED) {
                $event->addFlashMessages(
                    $this->translator->translate('users.authenticator.access_token.autologin_disabled'),
                    'notice'
                );
            }
        }
    }
}
