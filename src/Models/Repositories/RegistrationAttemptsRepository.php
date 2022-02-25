<?php

namespace Crm\UsersModule\Repository;

use Crm\ApplicationModule\Repository;
use Nette\Utils\DateTime;
use Sinergi\BrowserDetector\Device;
use Sinergi\BrowserDetector\Os;
use Sinergi\BrowserDetector\UserAgent;

class RegistrationAttemptsRepository extends Repository
{
    public const STATUS_UNKNOWN = 'unknown';
    public const STATUS_OK = 'ok';
    public const STATUS_INVALID_EMAIL = 'invalid_email';
    public const STATUS_TAKEN_EMAIL = 'taken_email';
    public const STATUS_DEVICE_TOKEN_NOT_FOUND = 'device_token_not_found';
    public const STATUS_RATE_LIMIT_EXCEEDED = 'rate_limit_exceeded';
    
    protected $tableName = 'registration_attempts';

    final public function insertAttempt($email, $userId, $source, $status, $ip, $userAgent, $dateTime)
    {
        $os = null;
        $device = null;

        if ($userAgent) {
            $ua = new UserAgent($userAgent);
            $o = new Os($ua);
            $d = new Device($ua);

            $os = $o->getName();
            $device = $d->getName();
        }

        return $this->getTable()->insert([
            'email' => $email,
            'user_id' => $userId,
            'created_at' => $dateTime,
            'status' => $status,
            'source' => $source,
            'ip' => $ip,
            'user_agent' => $userAgent,
            'os' => $os,
            'device' => $device,
        ]);
    }

    final public function getAttemptsCount(string $ip, DateTime $from): int
    {
        return $this->getTable()->where([
                'ip' => $ip,
                'created_at > ?' => $from
            ])
            ->count('*');
    }
}
