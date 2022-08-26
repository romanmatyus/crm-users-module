<?php

namespace Crm\UsersModule\Components;

use Crm\ApplicationModule\Config\ApplicationConfig;
use Crm\ApplicationModule\Widget\BaseLazyWidget;
use Crm\ApplicationModule\Widget\LazyWidgetManager;
use Crm\UsersModule\Repository\UserConnectedAccountsRepository;
use Crm\UsersModule\Repository\UsersRepository;
use Nette\Localization\Translator;

class UserConnectedAccountsListWidget extends BaseLazyWidget
{
    private string $templateName = 'user_connected_accounts_list_widget.latte';

    private UsersRepository $usersRepository;

    private Translator $translator;

    private UserConnectedAccountsRepository $userConnectedAccountsRepository;

    private ApplicationConfig $applicationConfig;

    public function __construct(
        LazyWidgetManager $lazyWidgetManager,
        UsersRepository $usersRepository,
        UserConnectedAccountsRepository $userConnectedAccountsRepository,
        Translator $translator,
        ApplicationConfig $applicationConfig
    ) {
        parent::__construct($lazyWidgetManager);
        $this->usersRepository = $usersRepository;
        $this->userConnectedAccountsRepository = $userConnectedAccountsRepository;
        $this->translator = $translator;
        $this->applicationConfig = $applicationConfig;
    }

    public function identifier()
    {
        return 'userconnectedaccountslistwidget';
    }

    public function render($id)
    {
        $user = $this->usersRepository->find($id);
        $googleSignIn = $this->applicationConfig->get('google_sign_in_enabled');
        $appleSignIn = $this->applicationConfig->get('apple_sign_in_enabled');

        if (!$googleSignIn && !$appleSignIn) {
            return;
        }

        $connectedAccounts = [];
        if ($googleSignIn) {
            $connectedAccount = $this->userConnectedAccountsRepository->getForUser($user, $this->userConnectedAccountsRepository::TYPE_GOOGLE_SIGN_IN);
            if ($connectedAccount) {
                $connectedAccounts[] = $connectedAccount;
            }
        }
        if ($appleSignIn) {
            $connectedAccount = $this->userConnectedAccountsRepository->getForUser($user, $this->userConnectedAccountsRepository::TYPE_APPLE_SIGN_IN);
            if ($connectedAccount) {
                $connectedAccounts[] = $connectedAccount;
            }
        }

        $this->template->connectedAccounts = $connectedAccounts;
        $this->template->setFile(__DIR__ . '/' . $this->templateName);
        $this->template->render();
    }

    public function handleDisconnect($id)
    {
        $userConnectedAccount = $this->userConnectedAccountsRepository->getTable()->where(['id' => $id])->fetch();
        $this->userConnectedAccountsRepository->removeAccountForUser($userConnectedAccount->user, $id);
        $this->presenter->flashMessage($this->translator->translate('users.admin.user_connected_accounts_list_widget.flash_message'));
        $this->presenter->redirect('this');
    }
}
