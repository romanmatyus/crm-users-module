<?php

namespace Crm\UsersModule\Components;

use Crm\ApplicationModule\Widget\BaseWidget;
use Crm\ApplicationModule\Widget\WidgetManager;
use Crm\UsersModule\Repository\UsersRepository;

class RegisteredUsersStatWidget extends BaseWidget
{
    private $templateName = 'registered_users_stat_widget.latte';

    private $usersRepository;

    public function __construct(WidgetManager $widgetManager, UsersRepository $usersRepository)
    {
        parent::__construct($widgetManager);
        $this->usersRepository = $usersRepository;
    }

    public function identifier()
    {
        return 'registeredusersstatwidget';
    }

    public function render()
    {
        $this->template->totalUsers = $this->usersRepository->totalCount();
        $this->template->setFile(__DIR__ . DIRECTORY_SEPARATOR . $this->templateName);
        $this->template->render();
    }
}
