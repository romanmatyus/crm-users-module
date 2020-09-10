<?php

namespace Crm\UsersModule\User;

use Crm\ApplicationModule\DataProvider\DataProviderInterface;
use Nette\Database\Table\IRow;

interface ClaimUserDataProviderInterface extends DataProviderInterface
{
    /**
     * @param array $params {
     *   @type IRow $unclaimedUser
     *   @type IRow $loggedUser
     * }
     * @return void
     */
    public function provide(array $params): void;
}
