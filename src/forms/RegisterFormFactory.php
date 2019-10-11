<?php

namespace Crm\UsersModule\Forms;

use Crm\ApplicationModule\DataProvider\DataProviderManager;
use Crm\UsersModule\Auth\InvalidEmailException;
use Crm\UsersModule\Auth\UserManager;
use Crm\UsersModule\DataProvider\RegisterFormDataProviderInterface;
use Nette\Application\UI\Form;
use Nette\Localization\ITranslator;
use Nette\Security\User;
use Tomaj\Form\Renderer\BootstrapRenderer;

class RegisterFormFactory
{
    private $userManager;

    private $dataProviderManager;

    private $user;

    private $translator;

    public $onUserExists;

    public $onUserCreated;

    public function __construct(
        UserManager $userManager,
        DataProviderManager $dataProviderManager,
        ITranslator $translator,
        User $user
    ) {
        $this->userManager = $userManager;
        $this->dataProviderManager = $dataProviderManager;
        $this->user = $user;
        $this->translator = $translator;
    }

    public function create()
    {
        $form = new Form();
        $form->setTranslator($this->translator);
        $form->setRenderer(new BootstrapRenderer());
        $form->addText('email', 'users.form.register.email.label')
            ->setAttribute('autofocus')
            ->setRequired('users.form.register.email.required')
            ->setAttribute('placeholder', 'users.form.register.email.placeholder');

        /** @var RegisterFormDataProviderInterface[] $providers */
        $providers = $this->dataProviderManager->getProviders('users.dataprovider.register_form', RegisterFormDataProviderInterface::class);
        foreach ($providers as $sorting => $provider) {
            $form = $provider->provide(['form' => $form]);
        }

        $form->addSubmit('submit', 'users.form.register.submit');

        $form->onSuccess[] = [$this, 'formSucceeded'];

        return $form;
    }

    public function formSucceeded($form, $values)
    {
        if ($this->userManager->loadUserByEmail($values->email)) {
            if ($this->onUserExists) {
                ($this->onUserExists)($form, $values);
            }
            $form->addError('users.form.register.error.already_exists');
            return;
        }

        try {
            $user = $this->userManager->addNewUser($form->values['email']);
        } catch (InvalidEmailException $e) {
            $form->addError('users.form.register.error.invalid_email');
            return;
        }

        $this->user->login(['username' => $user->email, 'alwaysLogin' => true]);

        /** @var RegisterFormDataProviderInterface[] $providers */
        $providers = $this->dataProviderManager->getProviders('users.dataprovider.register_form', RegisterFormDataProviderInterface::class);
        foreach ($providers as $sorting => $provider) {
            $form = $provider->submit($user, $form);
        }

        if ($this->onUserCreated) {
            ($this->onUserCreated)($form, $values, $user);
        }
    }
}
