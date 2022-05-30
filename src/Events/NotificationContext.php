<?php

namespace Crm\UsersModule\Events;

class NotificationContext
{
    // common context value keys provided as constants
    const HERMES_MESSAGE_TYPE = 'hermes_message_type';
    const BEFORE_EVENT = 'before_event';

    private $context;

    /**
     * Context of the application that triggered the notification
     *
     * @param array $context
     */
    public function __construct(array $context)
    {
        $this->context = $context;
    }

    public function getContext(): array
    {
        return $this->context;
    }

    public function getContextValue($key)
    {
        return $this->context[$key] ?? null;
    }
}
