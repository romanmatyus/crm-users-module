<?php

namespace Crm\UsersModule\Presenters;

use Crm\ApplicationModule\Presenters\FrontendPresenter;
use Crm\ApplicationModule\User\DeleteUserData;
use Crm\ApplicationModule\User\DownloadUserData;
use Crm\UsersModule\Auth\Access\AccessToken;
use Crm\UsersModule\Auth\UserManager;
use Crm\UsersModule\Events\NotificationEvent;
use Crm\UsersModule\Forms\ChangePasswordFormFactory;
use Crm\UsersModule\Forms\RequestPasswordFormFactory;
use Crm\UsersModule\Forms\ResetPasswordFormFactory;
use Crm\UsersModule\Forms\UserDeleteFormFactory;
use Crm\UsersModule\Repository\PasswordResetTokensRepository;
use Crm\UsersModule\Repository\UserEmailConfirmationsRepository;
use Crm\UsersModule\User\ZipBuilder;
use Nette\Application\Responses\FileResponse;
use Nette\Forms\Form;
use Nette\Utils\Html;
use Nette\Utils\Json;

class UsersPresenter extends FrontendPresenter
{
    private $changePasswordFormFactory;

    private $downloadUserData;

    private $deleteUserData;

    private $requestPasswordFormFactory;

    private $resetPasswordFormFactory;

    private $passwordResetTokensRepository;

    private $zipBuilder;

    private $userDeleteFormFactory;

    private $userManager;

    private $accessToken;

    /** @var UserEmailConfirmationsRepository */
    private $userEmailConfirmationsRepository;

    public function __construct(
        ChangePasswordFormFactory $changePasswordFormFactory,
        DownloadUserData $downloadUserData,
        DeleteUserData $deleteUserData,
        RequestPasswordFormFactory $requestPasswordFormFactory,
        ResetPasswordFormFactory $resetPasswordFormFactory,
        PasswordResetTokensRepository $passwordResetTokensRepository,
        ZipBuilder $zipBuilder,
        UserDeleteFormFactory $userDeleteFormFactory,
        UserManager $userManager,
        AccessToken $accessToken,
        UserEmailConfirmationsRepository $userEmailConfirmationsRepository
    ) {
        parent::__construct();
        $this->changePasswordFormFactory = $changePasswordFormFactory;
        $this->downloadUserData = $downloadUserData;
        $this->deleteUserData = $deleteUserData;
        $this->requestPasswordFormFactory= $requestPasswordFormFactory;
        $this->resetPasswordFormFactory = $resetPasswordFormFactory;
        $this->passwordResetTokensRepository = $passwordResetTokensRepository;
        $this->zipBuilder = $zipBuilder;
        $this->userDeleteFormFactory = $userDeleteFormFactory;
        $this->userManager = $userManager;
        $this->accessToken = $accessToken;
        $this->userEmailConfirmationsRepository = $userEmailConfirmationsRepository;
    }

    public function renderProfile()
    {
        $this->onlyLoggedIn();
        $this->template->user = $this->getUser();
    }

    public function renderChangePassword()
    {
        if (!$this->getUser()->isLoggedIn()) {
            $this->redirect('request-password');
        }
    }

    public function renderResetPassword($id)
    {
        if ($this->getUser()->isLoggedIn()) {
            $this->getUser()->logout(true);
        }

        if (is_null($id)) {
            $this->redirect('requestPassword');
        }

        if (!$this->passwordResetTokensRepository->isAvailable($id)) {
            $this->flashMessage(
                $this->translator->translate('users.frontend.reset_password.errors.invalid_password_reset_token'),
                "error"
            );
            $this->redirect('requestPassword');
        }
    }

    public function renderRequestPassword()
    {
        if ($this->getUser()->isLoggedIn()) {
            $this->redirect($this->homeRoute);
        }
    }

    public function createComponentChangePasswordForm()
    {
        $form = $this->changePasswordFormFactory->create($this->getUser());

        $form['actual_password']
            ->setOption(
                'description',
                Html::el('span', ['class' => 'help-block'])
                    ->setHtml(
                        $this->translator->translate(
                            'users.frontend.change_password.actual_password.description',
                            ['url' => $this->link('EmailReset!')]
                        )
                    )
            );
        $this->changePasswordFormFactory->onSuccess = function ($devicesLogout = false) {
            if ($devicesLogout) {
                $this->flashMessage($this->translator->translate('users.frontend.change_password.success_with_logout'));
            } else {
                $this->flashMessage($this->translator->translate('users.frontend.change_password.success'));
            }

            $this->redirect($this->homeRoute);
        };
        return $form;
    }

    public function createComponentRequestPasswordForm()
    {
        $form = $this->requestPasswordFormFactory->create();
        $this->requestPasswordFormFactory->onSuccess = function (string $email) {
            $sessionSection = $this->session->getSection('request_password_success');
            $sessionSection->email = $email;
            $this->redirect('requestPasswordSuccessInfo');
        };
        return $form;
    }

    public function createComponentResetPasswordForm()
    {
        $token = '';
        if (isset($this->params['id'])) {
            $token = $this->params['id'];
        }
        $form = $this->resetPasswordFormFactory->create($token);
        $this->resetPasswordFormFactory->onSuccess = function () {
            $this->flashMessage($this->translator->translate('users.frontend.reset_password.success'));
            $this->redirect(':Users:Sign:In');
        };
        return $form;
    }

    public function renderSettings()
    {
        $this->onlyLoggedIn();
        list($this->template->canBeDeleted, $_) = $this->deleteUserData->canBeDeleted($this->getUser()->getId());
    }

    public function handleDownloadData()
    {
        $this->onlyLoggedIn();
        set_time_limit(120);

        $zip = $this->zipBuilder->getZipFile();
        $fileName = $zip->filename;

        // text data
        $userData = $this->downloadUserData->getData($this->getUser()->getId());
        $zip->addFromString('data.json', Json::encode($userData));

        // file attachments
        foreach ($this->downloadUserData->getAttachments($this->getUser()->getId()) as $attachmentName => $attachmentPath) {
            $zip->addFile($attachmentPath, $attachmentName);
        }

        $zip->close();
        clearstatcache();

        $this->sendResponse(new FileResponse($fileName, 'data.zip', 'application/zip', true));
    }

    public function handleDevicesLogout()
    {
        $this->onlyLoggedIn();
        $accessToken = $this->accessToken->getToken($this->getHttpRequest());

        $user = $this->usersRepository->find($this->getUser()->getId());

        $this->userManager->logoutUser($user, [$accessToken]);
        $this->flashMessage($this->translator->translate('users.frontend.settings.devices_logout.success'));
    }

    public function createComponentUserDeleteForm()
    {
        $form = $this->userDeleteFormFactory->create($this->getUser()->getId());
        $form->onError[] = function (Form $form) {
            $this->flashMessage($form->getErrors()[0], 'error');
        };

        $this->userDeleteFormFactory->onSuccess = function () {
            $this->getUser()->logout(true);
            $this->flashMessage($this->translator->translate('users.frontend.settings.account_delete.success'));
            $this->redirect(':Users:Sign:In');
        };

        return $form;
    }

    public function handleEmailReset()
    {
        $this->onlyLoggedIn();
        $user = $this->userManager->loadUser($this->getUser());
        $newPassword = $this->userManager->resetPassword($user);

        $this->emitter->emit(new NotificationEvent(
            $this->emitter,
            $user,
            'reset_password_with_password',
            [
                'email' => $user->email,
                'password' => $newPassword,
            ]
        ));

        $this->flashMessage($this->translator->translate('users.frontend.change_password.reset_success', ['email' => $user->email]));
        $this->redirect('this');
    }

    public function renderRequestPasswordSuccessInfo()
    {
        $sessionSection = $this->session->getSection('request_password_success');
        $email = $sessionSection->email;
        unset($sessionSection->email);
        if (!$email) {
            $this->redirect('requestPassword');
        }
        $this->template->email = $email;
    }

    public function renderEmailConfirm(string $token)
    {
        $verificationResult = $this->userEmailConfirmationsRepository->verify($token);

        $this->template->verificationResult = $verificationResult;
    }
}
