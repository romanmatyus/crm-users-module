<?php

namespace Crm\UsersModule\DataProvider;

use Crm\ApplicationModule\DataProvider\DataProviderInterface;
use Nette\Application\UI\Form;

interface UserFormDataProviderInterface extends DataProviderInterface
{
    public function provide(array $params): Form;
}
