<?php

namespace Crm\UsersModule\User;

use Crm\ApplicationModule\DataProvider\DataProviderInterface;
use Nette\Database\Table\IRow;

interface ClaimUserDataProviderInterface extends DataProviderInterface
{
    /**
     * @param array{unclaimedUser: IRow, loggedUser: IRow} $params
     */
    public function provide(array $params): void;
}
