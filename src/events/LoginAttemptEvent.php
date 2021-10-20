<?php

namespace Crm\UsersModule\Events;

use League\Event\AbstractEvent;
use Nette\Database\Table\ActiveRow;
use Nette\Security\User;

class LoginAttemptEvent extends AbstractEvent
{
    private $user;

    private $email;

    private $source;

    private $status;

    private $message;

    private $date;

    /**
     * LoginAttemptEvent constructor.
     *
     * @param string $email
     * @param ActiveRow|User|null $user
     * @param string $source
     * @param string $status
     * @param string|null $message
     * @param \DateTime|null $date
     */
    public function __construct($email, $user, $source, $status, $message = null, $date = null)
    {
        $this->email = $email;
        $this->user = $user;
        $this->source = $source;
        $this->status = $status;
        $this->message = $message;
        $this->date = $date != null ? $date : new \DateTime();
    }

    public function getEmail()
    {
        return $this->email;
    }

    public function getUser()
    {
        return $this->user;
    }

    public function getSource()
    {
        return $this->source;
    }

    public function getStatus()
    {
        return $this->status;
    }

    public function getMessage()
    {
        return $this->message;
    }

    public function getDate()
    {
        return $this->date;
    }
}
