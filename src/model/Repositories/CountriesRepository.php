<?php

namespace Crm\UsersModule\Repository;

use Crm\ApplicationModule\Repository;
use Nette\Database\Context;

class CountriesRepository extends Repository
{
    protected $tableName = 'countries';

    private $defaultCountry;

    private $defaultCountryISO;

    public function __construct(
        $defaultCountryISO,
        Context $database
    ) {
        parent::__construct($database);
        $this->setDefaultCountry($defaultCountryISO);
    }

    private function setDefaultCountry($defaultCountryISO)
    {
        if (!isset($defaultCountryISO)) {
            throw new \Exception("Missing environment variable `CRM_DEFAULT_COUNTRY_ISO`");
        }
        $this->defaultCountryISO = $defaultCountryISO;

        $country = $this->findBy('iso_code', $defaultCountryISO);
        $this->defaultCountry = $country;
    }

    final public function defaultCountry()
    {
        if (!$this->defaultCountry) {
            throw new \Exception("Unable to load default country from provided ISO code `{$this->defaultCountryISO}`");
        }
        return $this->defaultCountry;
    }

    final public function all()
    {
        return $this->getTable()->order('-sorting DESC, name');
    }

    final public function getAllPairs()
    {
        return $this->all()->fetchPairs('id', 'name');
    }

    final public function getAllIsoPairs()
    {
        return $this->all()->fetchPairs('id', 'iso_code');
    }

    final public function getDefaultCountryPair()
    {
        $default = $this->defaultCountry();
        return [$default->id => $default->name];
    }

    final public function findByName($countryName)
    {
        return $this->findBy('name', $countryName);
    }

    final public function findByIsoCode($isoCode)
    {
        return $this->findBy('iso_code', $isoCode);
    }

    final public function add(string $isoCode, string $name, ?int $sorting)
    {
        return $this->insert([
            'iso_code' => $isoCode,
            'name' => $name,
            'sorting' => $sorting
        ]);
    }

    final public function exists($code)
    {
        return $this->getTable()->where('iso_code', $code)->count('*') > 0;
    }
}
