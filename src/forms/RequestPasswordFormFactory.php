<?php

namespace Crm\UsersModule\Forms;

use Crm\UsersModule\Auth\UserManager;
use Nette\Application\UI\Form;
use Nette\Localization\ITranslator;
use Tomaj\Form\Renderer\BootstrapRenderer;

class RequestPasswordFormFactory
{
    private $userManager;

    private $translator;

    /* callback function */
    public $onSuccess;

    public function __construct(UserManager $userManager, ITranslator $translator)
    {
        $this->userManager = $userManager;
        $this->translator = $translator;
    }

    /**
     * @return Form
     */
    public function create()
    {
        $form = new Form;

        $form->setRenderer(new BootstrapRenderer());
        $form->addProtection();

        $form->addText('email', $this->translator->translate('users.frontend.request_password.email.label'))
            ->setType('email')
            ->setAttribute('autofocus')
            ->setRequired($this->translator->translate('users.frontend.request_password.email.required'))
            ->setAttribute('placeholder', $this->translator->translate('users.frontend.request_password.email.placeholder'));

        $form->addSubmit('send', $this->translator->translate('users.frontend.request_password.submit'));

        $form->onSuccess[] = [$this, 'formSucceeded'];
        return $form;
    }

    public function formSucceeded($form, $values)
    {
        $result = $this->userManager->requestResetPassword($values->email);

        if (!$result) {
            $form['email']->addError($this->translator->translate('users.frontend.request_password.invalid_email'));
        } else {
            $this->onSuccess->__invoke();
        }
    }
}
