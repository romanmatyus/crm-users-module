<?php

namespace Crm\UsersModule\Presenters;

use Crm\ApplicationModule\Presenters\FrontendPresenter;
use Crm\UsersModule\Auth\Sso\AppleSignIn;
use Crm\UsersModule\Auth\Sso\SsoException;
use Crm\UsersModule\Auth\Sso\SsoRedirectValidator;
use Tracy\Debugger;

class ApplePresenter extends FrontendPresenter
{
    private const SESSION_SECTION = 'apple_presenter';

    private $appleSignIn;

    private $ssoRedirectValidator;

    public function __construct(
        AppleSignIn $appleSignIn,
        SsoRedirectValidator $ssoRedirectValidator
    ) {
        $this->appleSignIn = $appleSignIn;
        $this->ssoRedirectValidator = $ssoRedirectValidator;
    }

    public function actionSign()
    {
        if (!$this->appleSignIn->isEnabled()) {
            $this->redirect('Sign:in');
        }

        $session = $this->getSession(self::SESSION_SECTION);
        unset($session->finalUrl);

        // Final URL destination
        $finalUrl = $this->getParameter('url');
        if ($finalUrl) {
            $refererUrl = $this->getHttpRequest()->getReferer();

            if ($this->ssoRedirectValidator->isAllowed($finalUrl)) {
                $session->finalUrl = $finalUrl;
            } elseif ($refererUrl && $this->ssoRedirectValidator->isAllowed($refererUrl->getAbsoluteUrl())) {
                // Redirect backup to Referer (if provided 'url' parameter is invalid or manipulated)
                $session->finalUrl = $refererUrl->getAbsoluteUrl();
            }
        }

        try {
            // redirect URL is your.crm.url/users/apple/callback
            $authUrl = $this->appleSignIn->signInRedirect($this->link('//callback'));
            // redirect user to apple authentication
            $this->redirectUrl($authUrl);
        } catch (SsoException $e) {
            Debugger::log($e->getMessage(), Debugger::WARNING);
            $this->flashMessage($this->translator->translate('users.frontend.apple.fail'), 'danger');
            $this->redirect('Sign:in');
        }
    }

    public function actionCallback()
    {
        if (!$this->appleSignIn->isEnabled()) {
            $this->redirect('Sign:in');
        }

        try {
            $user = $this->appleSignIn->signInCallback();

            // AutoLogin will log in user - create access token and set user flag (in session) to authenticated
            $this->getUser()->login([
                'user' => $user,
                'autoLogin' => true,
                'source' => AppleSignIn::ACCESS_TOKEN_SOURCE_WEB_APPLE_SSO,
            ]);
        } catch (SsoException $e) {
            Debugger::log($e, Debugger::WARNING);
            $this->flashMessage($this->translator->translate('users.frontend.apple.fail'), 'danger');
            $this->redirect('Sign:in');
        }

        $session = $this->getSession(self::SESSION_SECTION);
        $finalUrl = $session->finalUrl;
        if ($finalUrl) {
            $this->redirectUrl($finalUrl);
        } else {
            $this->redirect($this->homeRoute);
        }
    }
}
