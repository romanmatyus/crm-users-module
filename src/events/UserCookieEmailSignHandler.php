<?php

namespace Crm\UsersModule\Events;

use Crm\ApplicationModule\Request as AppRequest;
use League\Event\AbstractListener;
use League\Event\EventInterface;
use Nette\Http\Response;

class UserCookieEmailSignHandler extends AbstractListener
{
    private $response;

    public function __construct(Response $response)
    {
        $this->response = $response;
    }

    public function handle(EventInterface $event)
    {
        if ($event instanceof \Crm\UsersModule\Events\UserSignInEvent) {
            $user = $event->getUser();
            $timeout = '30 days';

            $this->response->setCookie(
                'n_email',
                $user->email,
                strtotime($timeout),
                '/',
                AppRequest::getDomain(),
                null,
                false
            );
        } elseif ($event  instanceof  \Crm\UsersModule\Events\UserSignOutEvent) {
            $this->response->deleteCookie('n_email', '/', AppRequest::getDomain());
        }
    }
}
