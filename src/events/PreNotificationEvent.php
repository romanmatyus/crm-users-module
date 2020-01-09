<?php

namespace Crm\UsersModule\Events;

use League\Event\AbstractEvent;

/**
 * Class wrapping notification event
 * It gives ability to modify the notification (e.g. to add additional template parameters before it's sent)
 */
class PreNotificationEvent extends AbstractEvent
{
    private $notificationEvent;

    public function __construct(NotificationEvent $notificationEvent)
    {
        $this->notificationEvent = $notificationEvent;
    }

    public function getNotificationEvent(): NotificationEvent
    {
        return $this->notificationEvent;
    }
}
