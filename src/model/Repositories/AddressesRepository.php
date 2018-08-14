<?php

namespace Crm\UsersModule\Repository;

use Crm\ApplicationModule\Repository;
use Crm\ApplicationModule\Repository\AuditLogRepository;
use Nette\Database\Context;
use Nette\Database\Table\IRow;
use Nette\Utils\DateTime;

class AddressesRepository extends Repository
{
    protected $tableName = 'addresses';

    public function __construct(Context $database, AuditLogRepository $auditLogRepository)
    {
        parent::__construct($database);
        $this->auditLogRepository = $auditLogRepository;
    }

    public function add(IRow $user, $type, $firstName, $lastName, $address, $number, $city, $zip, $countryId, $phoneNumber, $ico = null, $dic = null, $icDph = null, $companyName = null, $title = null)
    {
        return $this->insert([
            'user_id' => $user->id,
            'type' => $type,
            'title' => $title,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'address' => $address,
            'number' => $number,
            'city' => $city,
            'zip' => $zip,
            'phone_number' => $phoneNumber,
            'country_id' => $countryId,
            'ico' => $ico,
            'dic' => $dic,
            'icdph' => $icDph,
            'company_name' => $companyName,
            'created_at' => new DateTime(),
            'updated_at' => new DateTime(),
        ]);
    }

    public function address(IRow $user, $type)
    {
        return $this->getTable()->where(['user_id' => $user->id, 'type' => $type])->order('updated_at DESC')->limit(1)->fetch();
    }

    public function addresses(IRow $user, $type = false)
    {
        $where = ['user_id' => $user->id];
        if ($type) {
            $where['type'] = $type;
        }
        return $this->getTable()->where($where)->fetchAll();
    }

    public function addressesSelect(IRow $user, $type)
    {
        $rows = $this->addresses($user, $type);
        $result = [];
        foreach ($rows as $row) {
            $result[$row->id] = "[{$row->type}] {$row->first_name} {$row->last_name}, {$row->address} {$row->zip} {$row->city}";
        }
        return $result;
    }

    public function update(IRow &$row, $data)
    {
        $data['updated_at'] = new DateTime();
        return parent::update($row, $data);
    }

    public function findByAddress($address, $type, $userId)
    {
        $addressMap = [
            'first_name' => null,
            'last_name' => null,
            'address' => null,
            'number' => null,
            'city' => null,
            'zip' => null,
            'country_id' => null,
            'ico' => null,
            'dic' => null,
            'icdph' => null,
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

        return $this->getTable()->where($addressMap)->fetch();
    }
}
