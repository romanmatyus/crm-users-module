<?php

namespace Crm\UsersModule\Forms;

use Crm\UsersModule\Auth\UserManager;
use Crm\UsersModule\Repository\PasswordResetTokensRepository;
use Nette\Application\UI\Form;
use Nette\Localization\ITranslator;
use Tomaj\Form\Renderer\BootstrapRenderer;

class ResetPasswordFormFactory
{
    private $userManager;

    private $passwordResetTokensRepository;

    private $translator;

    /* callback function */
    public $onSuccess;

    public function __construct(
        UserManager $userManager,
        PasswordResetTokensRepository $passwordResetTokensRepository,
        ITranslator $translator
    ) {
        $this->userManager = $userManager;
        $this->passwordResetTokensRepository = $passwordResetTokensRepository;
        $this->translator = $translator;
    }

    /**
     * @return Form
     */
    public function create($token)
    {
        $form = new Form;

        $form->setRenderer(new BootstrapRenderer());
        $form->setTranslator($this->translator);
        $form->addProtection();

        $form->addHidden('token', $token);

        $form->addPassword('new_password', 'users.frontend.reset_password.new_password.label')
            ->setRequired('users.frontend.reset_password.new_password.required')
            ->setAttribute('placeholder', 'users.frontend.reset_password.new_password.placeholder')
            ->addRule(Form::MIN_LENGTH, 'users.frontend.reset_password.new_password.min_length', 6);

        $form->addPassword('new_password_confirm', 'users.frontend.reset_password.new_password_confirm.placeholder')
            ->setRequired('users.frontend.reset_password.new_password_confirm.required')
            ->addRule(Form::EQUAL, 'users.frontend.reset_password.new_password_confirm.not_matching', $form['new_password'])
            ->setAttribute('placeholder', 'users.frontend.reset_password.new_password_confirm.placeholder')
            ->setOption('description', 'users.frontend.reset_password.new_password_confirm.description');

        $form->addSubmit('send', 'users.frontend.reset_password.submit');

        $form->onSuccess[] = [$this, 'formSucceeded'];
        return $form;
    }

    public function formSucceeded($form, $values)
    {
        $token = $this->passwordResetTokensRepository->loadAvailableToken($values->token);
        if (!$token) {
            $form['new_password']->addError('users.frontend.reset_password.could_not_set');
            return;
        }

        $user = $token->user;

        $result = $this->userManager->resetPassword($user, $values->new_password);

        $this->passwordResetTokensRepository->markUsed($token->token);

        if (!$result) {
            $form['new_password']->addError('users.frontend.reset_password.could_not_set');
        } else {
            $this->onSuccess->__invoke();
        }
    }
}
