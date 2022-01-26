<?php

namespace Crm\UsersModule\Forms;

use Crm\ApplicationModule\DataProvider\DataProviderManager;
use Crm\UsersModule\Auth\Authorizator;
use Crm\UsersModule\Auth\UserManager;
use Nette\Application\UI\Form;
use Nette\Localization\ITranslator;
use Nette\Security\AuthenticationException;
use Nette\Security\User;
use Tomaj\Form\Renderer\BootstrapRenderer;

class SignInFormFactory
{
    private $userManager;

    private $dataProviderManager;

    private $user;

    private $translator;

    private $authorizator;

    public $onAuthenticated;

    public function __construct(
        UserManager $userManager,
        DataProviderManager $dataProviderManager,
        ITranslator $translator,
        Authorizator $authorizator,
        User $user
    ) {
        $this->userManager = $userManager;
        $this->dataProviderManager = $dataProviderManager;
        $this->user = $user;
        $this->authorizator = $authorizator;
        $this->translator = $translator;
    }

    public function create($email = null)
    {
        $form = new Form();
        $form->setRenderer(new BootstrapRenderer());
        $form->addProtection();
        $username = $form->addText('username', $this->translator->translate('users.frontend.sign_in.username.label'))
            ->setType('email')
            ->setRequired($this->translator->translate('users.frontend.sign_in.username.required'))
            ->setAttribute('placeholder', $this->translator->translate('users.frontend.sign_in.username.placeholder'));

        $password = $form->addPassword('password', $this->translator->translate('users.frontend.sign_in.password.label'))
            ->setRequired($this->translator->translate('users.frontend.sign_in.password.required'))
            ->setAttribute('placeholder', $this->translator->translate('users.frontend.sign_in.password.required'));

        if ($username) {
            $password->setAttribute('autofocus');
        } else {
            $username->setAttribute('autofocus');
        }

        $form->addCheckbox('remember', $this->translator->translate('users.frontend.sign_in.remember'));

        $form->addSubmit('send', $this->translator->translate('users.frontend.sign_in.submit'));

        $form->setDefaults([
            'username' => $email,
            'remember' => true,
        ]);

        $form->onSuccess[] = [$this, 'formSucceeded'];
        return $form;
    }

    public function formSucceeded($form, $values)
    {
        if ($values->remember) {
            $this->user->setExpiration('14 days', false);
        } else {
            $this->user->setExpiration('20 minutes', true);
        }

        try {
            $this->user->login(['username' => $values->username, 'password' => $values->password]);
            $this->user->setAuthorizator($this->authorizator);
            ($this->onAuthenticated)($form, $values, $this->user);
        } catch (AuthenticationException $e) {
            $form->addError($e->getMessage());
        }
    }
}
