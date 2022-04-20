<?php

namespace Crm\UsersModule\Presenters;

use Crm\ApplicationModule\Presenters\FrontendPresenter;
use Crm\UsersModule\Auth\SignInRedirectValidator;
use Crm\UsersModule\Auth\Sso\AlreadyLinkedAccountSsoException;
use Crm\UsersModule\Auth\Sso\GoogleSignIn;
use Crm\UsersModule\Auth\Sso\SsoException;
use Tracy\Debugger;

class GooglePresenter extends FrontendPresenter
{
    private const SESSION_SECTION = 'google_presenter';

    private $googleSignIn;

    private $signInRedirectValidator;

    public function __construct(
        GoogleSignIn $googleSignIn,
        SignInRedirectValidator $signInRedirectValidator
    ) {
        parent::__construct();
        $this->googleSignIn = $googleSignIn;
        $this->signInRedirectValidator = $signInRedirectValidator;
    }

    public function actionSign()
    {
        if (!$this->googleSignIn->isEnabled()) {
            $this->redirect('Sign:in');
        }

        $session = $this->getSession(self::SESSION_SECTION);
        unset($session->finalUrl);
        unset($session->referer);
        unset($session->back);

        // Final URL destination
        $finalUrl = $this->getParameter('url');
        $referer = $this->getReferer();

        // remove locale from URL; it is already part of final url / referer and it breaks callback URL
        $this->locale = null;

        if ($finalUrl && $this->signInRedirectValidator->isAllowed($finalUrl)) {
            $session->finalUrl = $finalUrl;
        } elseif ($referer && $this->signInRedirectValidator->isAllowed($referer)) {
            // Redirect backup to Referer (if provided 'url' parameter is invalid or manipulated)
            $session->finalUrl = $referer;
        }
        if ($this->getParameter('back')) {
            $session->back = $this->getParameter('back');
        }

        // Save referer
        if ($referer) {
            $session->referer = $referer;
        }

        $source = $this->getParameter('n_source');

        try {
            // redirect URL is your.crm.url/users/google/callback
            $authUrl = $this->googleSignIn->signInRedirect($this->link('//callback'), $source);
            // redirect user to google authentication
            $this->redirectUrl($authUrl);
        } catch (SsoException $e) {
            Debugger::log($e->getMessage(), Debugger::WARNING);
            $this->flashMessage($this->translator->translate('users.frontend.google.fail'));
            $this->redirect('Sign:in');
        }
    }

    public function actionCallback()
    {
        if (!$this->googleSignIn->isEnabled()) {
            $this->redirect('Sign:in');
        }

        $session = $this->getSession(self::SESSION_SECTION);
        $referer = $session->referer;

        try {
            $user = $this->googleSignIn->signInCallback($this->link('//callback'), $referer);

            if (!$this->getUser()->isLoggedIn()) {
                // AutoLogin will log in user - create access token and set user flag (in session) to authenticated
                $this->getUser()->login([
                    'user' => $user,
                    'autoLogin' => true,
                    'source' => GoogleSignIn::ACCESS_TOKEN_SOURCE_WEB_GOOGLE_SSO,
                ]);
            }
        } catch (SsoException $e) {
            Debugger::log($e, Debugger::WARNING);
            $this->flashMessage($this->translator->translate('users.frontend.google.fail'), 'error');
            $this->redirect('Users:settings');
        } catch (AlreadyLinkedAccountSsoException $e) {
            $this->flashMessage($this->translator->translate('users.frontend.google.used_account', [
                'email' => $e->getEmail(),
            ]), 'error');
            $this->redirect('Users:settings');
        }

        $back = $session->back;
        $finalUrl = $session->finalUrl;

        if ($back) {
            $this->restoreRequest($back);
        }

        if ($finalUrl) {
            $this->redirectUrl($finalUrl);
        } else {
            $this->redirect($this->homeRoute);
        }
    }
}
