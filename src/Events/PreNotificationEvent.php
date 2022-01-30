<?php

namespace Crm\UsersModule\Events;

use League\Event\AbstractEvent;

/**
 * Class wrapping notification event
 * It gives ability to modify the notification (e.g. to add additional template parameters before it's sent)
 *
 */
class PreNotificationEvent extends AbstractEvent
{
    private $notificationEvent;

    private $context;

    /**
     * PreNotificationEvent constructor.
     *
     * @param NotificationEvent   $notificationEvent
     * @param NotificationContext $context
     */
    public function __construct(NotificationEvent $notificationEvent, ?NotificationContext $context = null)
    {
        $this->notificationEvent = $notificationEvent;
        $this->context = $context;
    }

    public function getNotificationEvent(): NotificationEvent
    {
        return $this->notificationEvent;
    }

    public function getNotificationContext(): ?NotificationContext
    {
        return $this->context;
    }
}
