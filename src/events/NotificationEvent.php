<?php

namespace Crm\UsersModule\Events;

use League\Event\AbstractEvent;
use League\Event\Emitter;
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
     * @param Emitter   $emitter
     * @param IRow      $user
     * @param string    $templateCode
     * @param array     $params
     * @param string    $context
     * @param array     $attachments
     * @param \DateTime $scheduleAt
     */
    public function __construct(
        Emitter $emitter,
        IRow $user,
        string $templateCode,
        array $params = [],
        string $context = null,
        array $attachments = [],
        \DateTime $scheduleAt = null
    ) {
        $this->user         = $user;
        $this->templateCode = $templateCode;
        $this->params       = $params;
        $this->context      = $context;
        $this->attachments  = $attachments;
        $this->scheduleAt   = $scheduleAt;

        // Let modules modify NotificationEvent parameters
        $emitter->emit(new PreNotificationEvent($this));
    }

    public function getUser(): IRow
    {
        return $this->user;
    }

    public function getTemplateCode(): string
    {
        return $this->templateCode;
    }

    public function getParams(): array
    {
        return $this->params;
    }

    public function getContext(): ?string
    {
        return $this->context;
    }

    public function getAttachments(): array
    {
        return $this->attachments;
    }

    public function getScheduleAt(): ?\DateTime
    {
        return $this->scheduleAt;
    }

    public function setUser(IRow $user): void
    {
        $this->user = $user;
    }

    public function setTemplateCode(string $templateCode): void
    {
        $this->templateCode = $templateCode;
    }

    public function setParams(array $params): void
    {
        $this->params = $params;
    }

    public function setContext(string $context): void
    {
        $this->context = $context;
    }

    public function setAttachments(array $attachments): void
    {
        $this->attachments = $attachments;
    }

    public function setScheduleAt(\DateTime $scheduleAt): void
    {
        $this->scheduleAt = $scheduleAt;
    }
}
