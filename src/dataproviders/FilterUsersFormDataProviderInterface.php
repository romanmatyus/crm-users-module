<?php

namespace Crm\UsersModule\DataProvider;

use Crm\ApplicationModule\DataProvider\DataProviderInterface;
use Nette\Application\UI\Form;

/**
 * FilterUsersFormDataProviderInterface is responsible for enhancing provided form with additional form elements
 *
 * Interface FilterUsersFormDataProviderInterface
 * @package Crm\UsersModule\DataProvider
 */
interface FilterUsersFormDataProviderInterface extends DataProviderInterface
{
    /**
     * @param array $params {
     *   @type Form $form
     * }
     * @return Form
     */
    public function provide(array $params): Form;
}
