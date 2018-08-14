<?php

namespace Crm\UsersModule\Forms;

use Crm\UsersModule\Events\AddressChangedEvent;
use Crm\UsersModule\Events\NewAddressEvent;
use Crm\UsersModule\Repository\AddressesRepository;
use Crm\UsersModule\Repository\AddressTypesRepository;
use Crm\UsersModule\Repository\CountriesRepository;
use Crm\UsersModule\Repository\UsersRepository;
use League\Event\Emitter;
use Nette\Application\UI\Form;
use Nette\Localization\ITranslator;
use Tomaj\Form\Renderer\BootstrapRenderer;

class AddressFormFactory
{
    private $userRepository;

    private $countriesRepository;

    private $addressesRepository;

    private $addressTypesRepository;

    private $emitter;

    private $translator;

    public $onSave;

    public $onUpdate;

    public function __construct(
        UsersRepository $userRepository,
        AddressesRepository $addressesRepository,
        CountriesRepository $countriesRepository,
        AddressTypesRepository $addressTypesRepository,
        Emitter $emitter,
        ITranslator $translator
    ) {
        $this->userRepository = $userRepository;
        $this->addressesRepository = $addressesRepository;
        $this->addressTypesRepository = $addressTypesRepository;
        $this->countriesRepository = $countriesRepository;
        $this->emitter = $emitter;
        $this->translator = $translator;
    }

    /**
     * @return Form
     */
    public function create($addressId, $userId)
    {
        $form = new Form;

        $form->setRenderer(new BootstrapRenderer());
        $form->addProtection();

        $defaults = [];
        $address = $this->addressesRepository->find($addressId);

        if ($addressId) {
            $defaults = $address->toArray();
            if (!$defaults['country_id']) {
                $defaults['country_id'] = $this->countriesRepository->defaultCountry()->id;
            }
            $userId = $address->user_id;
        } else {
            $defaults['country_id'] = $this->countriesRepository->defaultCountry()->id;
            $userRow = $this->userRepository->find($userId);
            $defaults['first_name'] = $userRow->first_name;
            $defaults['last_name'] = $userRow->last_name;
        }

        $form->addSelect('type', $this->translator->translate('users.frontend.address.type.label'), $this->addressTypesRepository->getPairs());

        $form->addText('first_name', $this->translator->translate('users.frontend.address.first_name.label'))
            ->setAttribute('placeholder', $this->translator->translate('users.frontend.address.first_name.placeholder'));
        $form->addText('last_name', $this->translator->translate('users.frontend.address.last_name.label'))
            ->setAttribute('placeholder', $this->translator->translate('users.frontend.address.first_name.placeholder'));

        $form->addText('phone_number', $this->translator->translate('users.frontend.address.phone_number.label'))
            ->setAttribute('placeholder', $this->translator->translate('users.frontend.address.phone_number.placeholder'));
        $form->addText('address', $this->translator->translate('users.frontend.address.address.label'))
            ->setAttribute('placeholder', $this->translator->translate('users.frontend.address.address.placeholder'));
        $form->addText('number', $this->translator->translate('users.frontend.address.number.label'))
            ->setAttribute('placeholder', $this->translator->translate('users.frontend.address.number.placeholder'));
        $form->addText('zip', $this->translator->translate('users.frontend.address.zip.label'))
            ->setAttribute('placeholder', $this->translator->translate('users.frontend.address.zip.placeholder'));
        $form->addText('city', $this->translator->translate('users.frontend.address.city.label'))
            ->setAttribute('placeholder', $this->translator->translate('users.frontend.address.city.placeholder'));
        $form->addSelect('country_id', $this->translator->translate('users.frontend.address.country.label'), $this->countriesRepository->getAllPairs());

        $form->addText('company_name', $this->translator->translate('users.frontend.address.company_name.label'))
            ->setAttribute('placeholder', $this->translator->translate('users.frontend.address.company_name.placeholder'));
        $form->addText('ico', $this->translator->translate('users.frontend.address.company_id.label'))
            ->setAttribute('placeholder', $this->translator->translate('users.frontend.address.company_id.placeholder'));
        $form->addText('dic', $this->translator->translate('users.frontend.address.company_tax_id.label'))
            ->setAttribute('placeholder', $this->translator->translate('users.frontend.address.company_tax_id.placeholder'));
        $form->addText('icdph', $this->translator->translate('users.frontend.address.company_vat_id.label'))
            ->setAttribute('placeholder', $this->translator->translate('users.frontend.address.company_vat_id.placeholder'));

        $form->addSubmit('send', $this->translator->translate('users.frontend.address.submit'))
            ->getControlPrototype()
            ->setName('button')
            ->setHtml('<i class="fa fa-save"></i> ' . $this->translator->translate('users.frontend.address.submit'));

        if ($userId) {
            $form->addHidden('user_id', $userId);
        }
        if ($addressId) {
            $form->addHidden('id', $addressId);
        }

        $form->setDefaults($defaults);

        $form->onSuccess[] = [$this, 'formSucceeded'];
        return $form;
    }

    public function formSucceeded($form, $values)
    {
        $user = $this->userRepository->find($values->user_id);

        if (isset($values->id)) {
            $address = $this->addressesRepository->find($values->id);
            $this->addressesRepository->update($address, $values);
            $this->emitter->emit(new AddressChangedEvent($address));
            $this->onUpdate->__invoke($form, $address);
        } else {
            $address = $this->addressesRepository->add(
                $user,
                $values->type,
                $values->first_name,
                $values->last_name,
                $values->address,
                $values->number,
                $values->city,
                $values->zip,
                $values->country_id,
                $values->phone_number,
                $values->ico,
                $values->dic,
                $values->icdph,
                $values->company_name
            );
            $this->emitter->emit(new NewAddressEvent($address));
            $this->onSave->__invoke($form, $address);
        }
    }
}
