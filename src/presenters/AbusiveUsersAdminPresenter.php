<?php

namespace Crm\UsersModule\Presenters;

use Crm\AdminModule\Presenters\AdminPresenter;
use Crm\ApplicationModule\Components\VisualPaginator;
use Crm\ApplicationModule\DataProvider\DataProviderManager;
use Crm\UsersModule\DataProvider\FilterAbusiveUserFormDataProviderInterface;
use Crm\UsersModule\Forms\AbusiveUsersFilterFormFactory;
use Crm\UsersModule\Repository\UsersRepository;
use Nette\Application\BadRequestException;
use Nette\Utils\DateTime;

class AbusiveUsersAdminPresenter extends AdminPresenter
{
    /** @persistent */
    public $email;

    /** @persistent */
    public $dateFrom;

    /** @persistent */
    public $dateTo;

    /** @persistent */
    public $loginCount;

    /** @persistent */
    public $deviceCount;

    /** @persistent */
    public $sortBy;

    /** @var UsersRepository @inject */
    public $usersRepository;

    /** @var AbusiveUsersFilterFormFactory @inject */
    public $abusiveUsersFilterFormFactory;

    /** @var DataProviderManager @inject */
    public $dataProviderManager;

    public function renderDefault($dateFrom, $dateTo, $loginCount = 10, $deviceCount = 1, $sortBy = 'device_count', $email = null)
    {
        $this->dateFrom = $dateFrom ?? (new DateTime())->modify('- 2 months')->format('Y-m-d');
        $this->dateTo = $dateTo ?? (new DateTime())->format('Y-m-d');

        $usersSelection = $this->usersRepository->getAbusiveUsers(
            new DateTime($this->dateFrom),
            new DateTime($this->dateTo),
            $loginCount,
            $deviceCount,
            $sortBy,
            $email
        );

        /** @var FilterAbusiveUserFormDataProviderInterface[] $providers */
        $providers = $this->dataProviderManager->getProviders('users.dataprovider.filter_abusive_user_form', FilterAbusiveUserFormDataProviderInterface::class);
        foreach ($providers as $sorting => $provider) {
            $usersSelection = $provider->filter($usersSelection, $this->params);
        }

        $filteredCount = (clone $usersSelection)->count();

        $vp = new VisualPaginator();
        $this->addComponent($vp, 'vp');
        $paginator = $vp->getPaginator();
        $paginator->setItemCount($filteredCount);
        $paginator->setItemsPerPage($this->onPage);

        $users = $usersSelection->limit($paginator->getLength(), $paginator->getOffset())->fetchAll();

        $this->template->filteredCount = $filteredCount;
        $this->template->vp = $vp;
        $this->template->abusers = $users;
        $this->template->sortByTokenCountLink = $this->link('AbusiveUsersAdmin:default', array_merge($this->getParameters(), ['sortBy' => 'token_count']));
        $this->template->sortByDeviceCountLink = $this->link('AbusiveUsersAdmin:default', array_merge($this->getParameters(), ['sortBy' => 'device_count']));
    }

    public function createComponentAbusiveUsersFilterForm()
    {
        $form = $this->abusiveUsersFilterFormFactory->create();
        $form->setDefaults([
            'email' => $this->email,
            'dateFrom' => $this->dateFrom,
            'dateTo' => $this->dateTo,
            'loginCount' => $this->loginCount,
            'deviceCount' => $this->deviceCount,
        ]);

        /** @var FilterAbusiveUserFormDataProviderInterface[] $providers */
        $providers = $this->dataProviderManager->getProviders('users.dataprovider.filter_abusive_user_form', FilterAbusiveUserFormDataProviderInterface::class);
        foreach ($providers as $sorting => $provider) {
            $form = $provider->provide(['form' => $form, 'params' => $this->params]);
        }

        $this->abusiveUsersFilterFormFactory->onCancel = function () use ($form) {
            $emptyDefaults = array_fill_keys(array_keys((array) $form->getComponents()), null);
            $this->redirect($this->action, $emptyDefaults);
        };

        $form->onSuccess[] = [$this, 'abusiveUsersFilterFormSucceeded'];

        return $form;
    }

    public function abusiveUsersFilterFormSucceeded($form, $values)
    {
        $this->redirect($this->action, array_filter((array) $values));
    }

    public function handleChangeActivation($userId)
    {
        $user = $this->usersRepository->find($userId);
        if (!$user) {
            throw new BadRequestException();
        }

        $this->usersRepository->toggleActivation($user);

        $this->flashMessage($this->translator->translate('users.admin.change_activation.activated'));
        $this->redirect('UsersAdmin:Show', $user->id);
    }
}
