<?php

namespace Crm\UsersModule\DataProvider;

use Crm\ApplicationModule\DataProvider\DataProviderInterface;

interface GoogleSignInDataProviderInterface extends DataProviderInterface
{
    public function provide(array $params): void;
}
