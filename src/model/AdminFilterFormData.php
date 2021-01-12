<?php

namespace Crm\UsersModule;

use Crm\ApplicationModule\DataProvider\DataProviderManager;
use Crm\UsersModule\DataProvider\FilterUsersFormDataProviderInterface;
use Crm\UsersModule\Repository\AddressesRepository;
use Crm\UsersModule\Repository\UsersRepository;
use Nette\Database\Table\Selection;

class AdminFilterFormData
{
    private $formData;

    private $addressesRepository;

    private $dataProviderManager;

    private $usersRepository;

    public function __construct(
        AddressesRepository $addressesRepository,
        DataProviderManager $dataProviderManager,
        UsersRepository $usersRepository
    ) {
        $this->dataProviderManager = $dataProviderManager;
        $this->usersRepository = $usersRepository;
        $this->addressesRepository = $addressesRepository;
    }

    public function parse($formData): void
    {
        $this->formData = $formData;
    }

    public function getFilteredUsers(): Selection
    {
        $users = $this->usersRepository
            ->all($this->getText())
            ->select('users.*')
            ->group('users.id');

        if ($this->getAddress()) {
            $users->where('users.id IN (?)', $this->getAddressQuery());
        }

        if ($this->getGroup()) {
            $users->where(':user_groups.group_id', (int)$this->getGroup());
        }
        if ($this->getSource()) {
            $users->where('users.source', $this->getSource());
        }

        /** @var FilterUsersFormDataProviderInterface[] $providers */
        $providers = $this->dataProviderManager->getProviders('users.dataprovider.users_filter_form', FilterUsersFormDataProviderInterface::class);
        foreach ($providers as $sorting => $provider) {
            $users = $provider->filter($users, $this->formData);
        }

        return $users;
    }

    public function getFormValues()
    {
        return [
            'text' => $this->getText(),
            'address' => $this->getAddress(),
            'group' => $this->getGroup(),
            'source' => $this->getSource()
        ];
    }

    private function getText()
    {
        return $this->formData['text'] ?? null;
    }

    private function getAddress()
    {
        return $this->formData['address'] ?? null;
    }

    private function getGroup()
    {
        return $this->formData['group'] ?? null;
    }

    private function getSource()
    {
        return $this->formData['source'] ?? null;
    }

    private function getAddressQuery(): Selection
    {
        $queryString = $this->getAddress();
        return $this->addressesRepository->all()->select('DISTINCT(user_id)')
            ->where(
                'address LIKE ? OR number LIKE ? OR city LIKE ? OR first_name LIKE ? OR last_name LIKE ?',
                [
                    "%{$queryString}%",
                    "%{$queryString}%",
                    "%{$queryString}%",
                    "%{$queryString}%",
                    "%{$queryString}%",
                ]
            );
    }
}
