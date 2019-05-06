<?php

namespace Crm\UsersModule\Components;

use Crm\ApplicationModule\Widget\BaseWidget;
use Crm\ApplicationModule\Widget\WidgetManager;
use Crm\UsersModule\Repository\UsersRepository;
use Nette\Utils\DateTime;

/**
 * This widget renders simple single stat widget with count of users registered today.
 *
 * @package Crm\UsersModule\Components
 */
class TodayUsersStatWidget extends BaseWidget
{
    private $templateName = 'today_users_stat_widget.latte';

    private $usersRepository;

    public function __construct(WidgetManager $widgetManager, UsersRepository $usersRepository)
    {
        parent::__construct($widgetManager);
        $this->usersRepository = $usersRepository;
    }

    public function identifier()
    {
        return 'todayusersstatwidget';
    }

    public function render()
    {
        $this->template->todayUsers = $this->usersRepository->usersRegisteredBetween(
            DateTime::from('today 00:00'),
            new DateTime()
        )->count('*');
        $this->template->yesterdayUsers = $this->usersRepository->usersRegisteredBetween(
            DateTime::from('yesterday 00:00'),
            DateTime::from('today 00:00')
        )->count('*');
        $this->template->setFile(__DIR__ . DIRECTORY_SEPARATOR . $this->templateName);
        $this->template->render();
    }
}
