<?php

namespace Crm\UsersModule\DataProvider;

use Crm\ApplicationModule\DataProvider\DataProviderException;
use Crm\ApplicationModule\DataProvider\DataProviderInterface;
use Nette\Database\IRow;

interface CanDeleteAddressDataProviderInterface extends DataProviderInterface
{
    /**
     * @param array{address: IRow} $params
     * @return array [
     *   'canDelete' => @type bool,
     *   'message' => @type string (Optional) Use for can't delete messages
     * ]
     * @throws DataProviderException
     */
    public function provide(array $params): array;
}
