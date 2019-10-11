<?php

namespace Crm\UsersModule\DataProvider;

use Crm\ApplicationModule\DataProvider\DataProviderInterface;
use Nette\Database\Table\IRow;
use Nette\Application\UI\Form;

interface RegisterFormDataProviderInterface extends DataProviderInterface
{
    public function provide(array $params): Form;

    public function submit(IRow $User, Form $form): Form;
}
