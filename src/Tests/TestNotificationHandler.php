<?php

namespace Crm\UsersModule\Tests;

use Crm\UsersModule\Events\NotificationEvent;
use League\Event\AbstractListener;
use League\Event\EventInterface;

class TestNotificationHandler extends AbstractListener
{
    /** @var NotificationEvent[][]  */
    private $notifications = [];

    public function handle(EventInterface $event)
    {
        if (!($event instanceof NotificationEvent)) {
            throw new \Exception('Unable to handle event, expected NotificationEvent');
        }

        $email = $event->getUser()->email;

        if (!array_key_exists($email, $this->notifications)) {
            $this->notifications[$email] = [];
        }

        $this->notifications[$email][] = $event;
    }

    /**
     * @param string $email
     *
     * @return NotificationEvent[]
     */
    public function notificationsSentTo(string $email): array
    {
        return $this->notifications[$email] ?? [];
    }
}
