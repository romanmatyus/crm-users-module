<?php

namespace Crm\UsersModule\Repository;

use Crm\ApplicationModule\DataProvider\DataProviderException;
use Crm\ApplicationModule\DataProvider\DataProviderManager;
use Crm\ApplicationModule\Repository;
use Crm\ApplicationModule\Repository\AuditLogRepository;
use Crm\UsersModule\DataProvider\CanDeleteAddressDataProviderInterface;
use Nette\Database\Context;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\IRow;
use Nette\Utils\DateTime;

class AddressesRepository extends Repository
{
    protected $tableName = 'addresses';

    private $dataProviderManager;

    public function __construct(
        Context $database,
        AuditLogRepository $auditLogRepository,
        DataProviderManager $dataProviderManager
    ) {
        parent::__construct($database);
        $this->auditLogRepository = $auditLogRepository;
        $this->dataProviderManager = $dataProviderManager;
    }

    final public function add(
        ActiveRow $user,
        string $type,
        ?string $firstName,
        ?string $lastName,
        ?string $address,
        ?string $number,
        ?string $city,
        ?string $zip,
        ?int $countryId,
        ?string $phoneNumber,
        ?string $companyName = null,
        ?string $companyId = null,
        ?string $companyTaxId = null,
        ?string $companyVatId = null
    ) {
        return $this->insert([
            'user_id' => $user->id,
            'type' => $type,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'address' => $address,
            'number' => $number,
            'city' => $city,
            'zip' => $zip,
            'phone_number' => $phoneNumber,
            'country_id' => $countryId,
            'company_name' => $companyName,
            'company_id' => str_replace(' ', '', $companyId),
            'company_tax_id' => str_replace(' ', '', $companyTaxId),
            'company_vat_id' => str_replace(' ', '', $companyVatId),
            'created_at' => new DateTime(),
            'updated_at' => new DateTime(),
        ]);
    }

    final public function address(IRow $user, $type)
    {
        return $this->getTable()
            ->where(['user_id' => $user->id, 'type' => $type])
            ->where('deleted_at IS NULL')
            ->order('updated_at DESC')->limit(1)->fetch();
    }

    final public function all()
    {
        return $this->getTable()->where('deleted_at IS NULL');
    }

    final public function addresses(IRow $user, $type = false)
    {
        $where = ['user_id' => $user->id];
        if ($type) {
            $where['type'] = $type;
        }
        return $this->getTable()
            ->where($where)
            ->where('deleted_at IS NULL')
            ->fetchAll();
    }

    final public function addressesSelect(IRow $user, $type)
    {
        $rows = $this->addresses($user, $type);
        $result = [];
        foreach ($rows as $row) {
            $result[$row->id] = "[{$row->type}] {$row->first_name} {$row->last_name}, {$row->address} {$row->number}, {$row->zip} {$row->city}";
        }
        return $result;
    }

    final public function update(IRow &$row, $data)
    {
        $data['updated_at'] = new DateTime();
        return parent::update($row, $data);
    }

    final public function findByAddress($address, $type, $userId)
    {
        $addressMap = [
            'first_name' => null,
            'last_name' => null,
            'address' => null,
            'number' => null,
            'city' => null,
            'zip' => null,
            'country_id' => null,
            'company_id' => null,
            'company_tax_id' => null,
            'company_vat_id' => null,
            'company_name' => null,
            'phone_number' => null,
            'type' => $type,
            'user_id' => $userId,
        ];

        foreach ($address as $key => $value) {
            if (array_key_exists($key, $addressMap)) {
                $addressMap[$key] = $value;
            }
        }

        return $this->getTable()->where($addressMap)->where('deleted_at IS NULL')->fetch();
    }

    /**
     * @param IRow $address
     * @return array
     * @throws DataProviderException
     */
    public function canDelete(IRow $address)
    {
        /** @var CanDeleteAddressDataProviderInterface[] $providers */
        $providers = $this->dataProviderManager->getProviders(
            'users.dataprovider.address.can_delete',
            CanDeleteAddressDataProviderInterface::class
        );
        foreach ($providers as $sorting => $provider) {
            $result = $provider->provide([
                'address' => $address
            ]);

            if (isset($result['canDelete']) && $result['canDelete'] === false) {
                return $result;
            }
        }

        return [
            'canDelete' => true
        ];
    }

    /**
     * @param IRow $address
     * @param bool $force
     * @throws \Exception
     */
    public function softDelete(IRow $address, $force = false)
    {
        if (!$force) {
            $check = $this->canDelete($address);
            if ($check['canDelete'] === false) {
                throw new CantDeleteAddressException($check['message']);
            }
        }

        $this->update($address, [
            'deleted_at' => new \DateTime(),
            'updated_at' => new \DateTime(),
        ]);
    }
}
