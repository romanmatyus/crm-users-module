<?php

namespace Crm\UsersModule\Components;

use Crm\ApplicationModule\Widget\BaseWidget;
use Crm\ApplicationModule\Widget\WidgetManager;

/**
 * This widget renders SSO login form
 *
 * @package Crm\UsersModule\Components
 */
class SsoWidget extends BaseWidget
{
    private $templateName = 'sso_widget.latte';

    public function __construct(WidgetManager $widgetManager)
    {
        parent::__construct($widgetManager);
    }

    public function identifier()
    {
        return 'ssowidget';
    }

    public function render($url = null)
    {
        return;

        // Currently, Google Sign In button in template is not allowed
        // TODO: enable once SSO button is ready in other templates (and Sign In is enabled in configuration)
//        $this->template->redirectUrl = $url;
        //$this->template->setFile(__DIR__ . '/' . $this->templateName);
        //$this->template->render();
    }
}
