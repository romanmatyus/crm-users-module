<?php

namespace Crm\UsersModule\Forms;

use Crm\UsersModule\Auth\UserManager;
use Nette\Application\UI\Form;
use Nette\Localization\ITranslator;
use Nette\Security\User;
use Tomaj\Form\Renderer\BootstrapRenderer;

class ChangePasswordFormFactory
{
    protected $userManager;

    protected $translator;

    /* callback function */
    public $onSuccess;

    /** @var  User */
    private $user;

    public function __construct(UserManager $userManager, ITranslator $translator)
    {
        $this->userManager = $userManager;
        $this->translator = $translator;
    }

    /**
     * @return Form
     */
    public function create(User $user)
    {
        $form = new Form;
        $this->user = $user;

        $form->setRenderer(new BootstrapRenderer());
        $form->addProtection();

        $form->addPassword('actual_password', $this->translator->translate('users.frontend.change_password.actual_password.label'))
            ->setAttribute('autofocus')
            ->setRequired($this->translator->translate('users.frontend.change_password.actual_password.required'))
            ->setAttribute('placeholder', $this->translator->translate('users.frontend.change_password.actual_password.placeholder'));

        $form->addPassword('new_password', $this->translator->translate('users.frontend.change_password.new_password.label'))
            ->setRequired($this->translator->translate('users.frontend.change_password.new_password.required'))
            ->setAttribute('placeholder', $this->translator->translate('users.frontend.change_password.new_password.placeholder'))
            ->addRule(Form::MIN_LENGTH, $this->translator->translate('users.frontend.change_password.new_password.minlength'), 6);

        $form->addPassword('new_password_confirm', $this->translator->translate('users.frontend.change_password.new_password_confirm.label'))
            ->setRequired($this->translator->translate('users.frontend.change_password.new_password_confirm.required'))
            ->addRule(Form::EQUAL, $this->translator->translate('users.frontend.change_password.new_password_confirm.not_matching'), $form['new_password'])
            ->setAttribute('placeholder', $this->translator->translate('users.frontend.change_password.new_password_confirm.placeholder'))
            ->setOption('description', $this->translator->translate('users.frontend.change_password.new_password_confirm.description'));

        $form->addSubmit('send', $this->translator->translate('users.frontend.change_password.submit'));

        $form->onSuccess[] = [$this, 'formSucceeded'];
        return $form;
    }

    public function formSucceeded($form, $values)
    {
        if (!$this->user->isLoggedIn()) {
            $form['actual_password']->addError($this->translator->translate('users.frontend.change_password.errors.could_not_authenticate'));
            return false;
        }

        $result = $this->userManager->setNewPassword(
            $this->user->getIdentity()->getId(),
            $values['actual_password'],
            $values['new_password']
        );

        if (!$result) {
            $form['actual_password']->addError($this->translator->translate('users.frontend.change_password.errors.invalid_credentials'));
        } else {
            // send email
            $this->onSuccess->__invoke();
        }
    }
}
