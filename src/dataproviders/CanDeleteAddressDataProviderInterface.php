<?php

namespace Crm\UsersModule\DataProvider;

use Crm\ApplicationModule\DataProvider\DataProviderException;
use Crm\ApplicationModule\DataProvider\DataProviderInterface;
use Nette\Database\IRow;

interface CanDeleteAddressDataProviderInterface extends DataProviderInterface
{
    /**
     * @param array $params {
     *   @type IRow $address
     * }
     * @return array {
     *   @type bool $canDelete
     *   @type string $message (optional - use for can't delete messages)
     * }
     * @throws DataProviderException
     */
    public function provide(array $params): array;
}
