<?php

namespace Crm\UsersModule\Events;

use League\Event\AbstractEvent;
use Nette\Database\IRow;

class NotificationEvent extends AbstractEvent
{
    private $user;

    private $templateCode;

    private $params;

    private $context;

    private $attachments;

    private $scheduleAt;

    /**
     * NotificationEvent constructor.
     *
     * @param IRow   $user
     * @param string $templateCode
     * @param array  $params
     * @param string $context
     * @param array  $attachments
     * @param string $scheduleAt RFC3339-formatted timestamp with notification execution time
     */
    public function __construct(
        IRow $user,
        string $templateCode,
        array $params = [],
        string $context = null,
        array $attachments = [],
        string $scheduleAt = null
    ) {
        $this->user         = $user;
        $this->templateCode = $templateCode;
        $this->params       = $params;
        $this->context      = $context;
        $this->attachments  = $attachments;
        $this->scheduleAt   = $scheduleAt;
    }

    /**
     * @return mixed
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * @return mixed
     */
    public function getTemplateCode()
    {
        return $this->templateCode;
    }

    /**
     * @return mixed
     */
    public function getParams()
    {
        return $this->params;
    }

    /**
     * @return mixed
     */
    public function getContext()
    {
        return $this->context;
    }

    /**
     * @return mixed
     */
    public function getAttachments()
    {
        return $this->attachments;
    }

    /**
     * @return mixed
     */
    public function getScheduleAt()
    {
        return $this->scheduleAt;
    }
}
