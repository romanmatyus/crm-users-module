<?php

namespace Crm\UsersModule\Authenticator;

use Crm\ApplicationModule\Authenticator\AuthenticatorInterface;
use Crm\ApplicationModule\Authenticator\BaseAuthenticator;
use Crm\UsersModule\Repository\AccessTokensRepository;
use Crm\UsersModule\Repository\LoginAttemptsRepository;
use Crm\UsersModule\Repository\UsersRepository;
use League\Event\Emitter;
use Nette\Database\Table\ActiveRow;
use Nette\Http\Request;
use Nette\Localization\ITranslator;
use Nette\Security\AuthenticationException;
use Nette\Security\IAuthenticator;

/**
 * AccessTokenAuthenticator authenticates user based on accessToken.
 *
 * Required credentials (use setCredentials()):
 *
 * - 'accessToken'
 */
class AccessTokenAuthenticator extends BaseAuthenticator
{
    private $accessTokensRepository;

    private $translator;

    /** @var string */
    private $accessToken = null;

    public function __construct(
        Emitter $emitter,
        \Tomaj\Hermes\Emitter $hermesEmitter,
        Request $request,
        AccessTokensRepository $accessTokensRepository,
        ITranslator $translator
    ) {
        parent::__construct($emitter, $hermesEmitter, $request);
        $this->translator = $translator;
        $this->accessTokensRepository = $accessTokensRepository;
    }

    public function authenticate()
    {
        if ($this->accessToken !== null) {
            return $this->process();
        }

        return false;
    }

    public function setCredentials(array $credentials) : AuthenticatorInterface
    {
        $this->accessToken = $credentials['accessToken'] ?? null;

        return $this;
    }

    public function shouldRegenerateToken(): bool
    {
        return false;
    }

    /**
     * @throws AuthenticationException
     */
    private function process() : ActiveRow
    {
        $tokenRow = $this->accessTokensRepository->loadToken($this->accessToken);
        if (!$tokenRow) {
            throw new AuthenticationException($this->translator->translate('users.authenticator.access_token.invalid_token'), IAuthenticator::FAILURE);
        }
        $user = $tokenRow->user;
        if (!$user) {
            throw new AuthenticationException($this->translator->translate('users.authenticator.access_token.invalid_token'), IAuthenticator::IDENTITY_NOT_FOUND);
        }
        if ($user->role === UsersRepository::ROLE_ADMIN) {
            throw new AuthenticationException($this->translator->translate('users.authenticator.access_token.autologin_disabled'), IAuthenticator::FAILURE);
        }

        $this->addAttempt($user->email, $user, $this->source, LoginAttemptsRepository::STATUS_ACCESS_TOKEN_OK);
        return $user;
    }
}
