<?php

namespace Crm\UsersModule\DataProvider;

use Crm\ApplicationModule\DataProvider\DataProviderInterface;
use Nette\Application\UI\Form;
use Nette\Database\Table\IRow;

interface RegisterFormDataProviderInterface extends DataProviderInterface
{
    public function provide(array $params): Form;

    public function submit(IRow $User, Form $form): Form;
}
