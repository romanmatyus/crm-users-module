<?php

namespace Crm\UsersModule\User;

use Crm\ApplicationModule\DataProvider\DataProviderInterface;
use Nette\Database\Table\ActiveRow;

interface ClaimUserDataProviderInterface extends DataProviderInterface
{
    /**
     * @param array{unclaimedUser: ActiveRow, loggedUser: ActiveRow} $params
     */
    public function provide(array $params): void;
}
