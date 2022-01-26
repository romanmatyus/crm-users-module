<?php

namespace Crm\UsersModule\Authenticator;

use Crm\ApplicationModule\Authenticator\AuthenticatorInterface;
use Crm\ApplicationModule\Authenticator\BaseAuthenticator;
use Crm\UsersModule\Auth\Rate\IpRateLimit;
use Crm\UsersModule\Auth\Rate\RateLimitException;
use Crm\UsersModule\Auth\Rate\WrongPasswordRateLimit;
use Crm\UsersModule\Auth\UserAuthenticator;
use Crm\UsersModule\Repository\LoginAttemptsRepository;
use Crm\UsersModule\Repository\UsersRepository;
use Crm\UsersModule\User\UnclaimedUser;
use League\Event\Emitter;
use Nette\Database\Table\IRow;
use Nette\Http\Request;
use Nette\Localization\ITranslator;
use Nette\Security\AuthenticationException;
use Nette\Security\Passwords;

/**
 * UsernameAuthenticator authenticates user based on username.
 *
 * Required credentials (use setCredentials()):

 * - 'username'
 * - 'password'
 */
abstract class UsernameAuthenticator extends BaseAuthenticator
{
    private $usersRepository;

    private $translator;

    private $wrongPasswordRateLimit;

    private $ipRateLimit;

    /** @var string */
    private $username = null;

    /** @var string */
    private $password = null;
    private UnclaimedUser $unclaimedUser;

    public function __construct(
        Emitter $emitter,
        \Tomaj\Hermes\Emitter $hermesEmitter,
        Request $request,
        UsersRepository $usersRepository,
        ITranslator $translator,
        WrongPasswordRateLimit $wrongPasswordRateLimit,
        IpRateLimit $ipRateLimit,
        UnclaimedUser $unclaimedUser
    ) {
        parent::__construct($emitter, $hermesEmitter, $request);

        $this->usersRepository = $usersRepository;
        $this->translator = $translator;
        $this->wrongPasswordRateLimit = $wrongPasswordRateLimit;
        $this->ipRateLimit = $ipRateLimit;
        $this->unclaimedUser = $unclaimedUser;
    }

    public function authenticate()
    {
        if ($this->username !== null && $this->password !== null) {
            return $this->process();
        }

        return false;
    }

    public function setCredentials(array $credentials) : AuthenticatorInterface
    {
        parent::setCredentials($credentials);

        $this->username = $credentials['username'] ?? null;
        $this->password = $credentials['password'] ?? null;

        return $this;
    }

    /**
     * @throws AuthenticationException
     */
    private function process() : IRow
    {
        if ($this->ipRateLimit->reachLimit(\Crm\ApplicationModule\Request::getIp())) {
            $this->addAttempt($this->username, null, $this->source, LoginAttemptsRepository::RATE_LIMIT_EXCEEDED, 'Rate limit exceeded.');
            throw new RateLimitException($this->translator->translate('users.authenticator.rate_limit_exceeded'), UserAuthenticator::FAILURE);
        }

        $user = $this->usersRepository->getByEmail($this->username);

        if (!$user) {
            $this->addAttempt($this->username, null, $this->source, LoginAttemptsRepository::STATUS_NOT_FOUND_EMAIL, 'Nesprávne meno.');
            throw new AuthenticationException($this->translator->translate('users.authenticator.identity_not_found'), UserAuthenticator::IDENTITY_NOT_FOUND);
        } elseif ($this->unclaimedUser->isUnclaimedUser($user)) {
            $this->addAttempt($this->username, $user, $this->source, LoginAttemptsRepository::STATUS_UNCLAIMED_USER, 'Account is unclaimed.');
            throw new AuthenticationException($this->translator->translate('users.authenticator.unclaimed_user'), UserAuthenticator::NOT_APPROVED);
        } elseif ($this->wrongPasswordRateLimit->reachLimit($user)) {
            $this->addAttempt($this->username, $user, $this->source, LoginAttemptsRepository::RATE_LIMIT_EXCEEDED, 'Rate limit exceeded.');
            throw new RateLimitException($this->translator->translate('users.authenticator.rate_limit_exceeded'), UserAuthenticator::FAILURE);
        } elseif (!$this->checkPassword($this->password, $user[UserAuthenticator::COLUMN_PASSWORD_HASH])) {
            $this->addAttempt($this->username, $user, $this->source, LoginAttemptsRepository::STATUS_WRONG_PASS, 'Heslo je nesprávne.');
            throw new AuthenticationException($this->translator->translate('users.authenticator.invalid_credentials'), UserAuthenticator::INVALID_CREDENTIAL);
        } elseif (!$user->active) {
            $this->addAttempt($this->username, $user, $this->source, LoginAttemptsRepository::STATUS_INACTIVE_USER, 'Konto je neaktívne.');
            throw new AuthenticationException($this->translator->translate('users.authenticator.inactive_account'), UserAuthenticator::IDENTITY_NOT_FOUND);
        } elseif (Passwords::needsRehash($user[UserAuthenticator::COLUMN_PASSWORD_HASH])) {
            $this->usersRepository->update($user, [
                UserAuthenticator::COLUMN_PASSWORD_HASH => Passwords::hash($this->password),
            ]);
        }

        if ($this->api) {
            $this->addAttempt($this->username, $user, $this->source, LoginAttemptsRepository::STATUS_API_OK);
        } else {
            $this->addAttempt($this->username, $user, $this->source, LoginAttemptsRepository::STATUS_OK);
        }

        $this->usersRepository->addSignIn($user);

        return $user;
    }

    protected function checkPassword($inputPassword, $passwordHash)
    {
        return Passwords::verify($inputPassword, $passwordHash);
    }
}
