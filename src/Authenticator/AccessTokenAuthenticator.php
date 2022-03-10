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
use Nette\Http\Session;
use Nette\Localization\Translator;
use Nette\Security\AuthenticationException;
use Nette\Security\Authenticator;

/**
 * AccessTokenAuthenticator authenticates user based on accessToken.
 *
 * Required credentials (use setCredentials()):
 *
 * - 'accessToken'
 */
class AccessTokenAuthenticator extends BaseAuthenticator
{
    public const SESSION_AUTH_DISABLED = 'disabled_tokens';

    private AccessTokensRepository $accessTokensRepository;

    private Translator $translator;

    private Session $session;

    /** @var string */
    private $accessToken = null;

    public function __construct(
        Emitter $emitter,
        \Tomaj\Hermes\Emitter $hermesEmitter,
        Request $request,
        AccessTokensRepository $accessTokensRepository,
        Translator $translator,
        Session $session
    ) {
        parent::__construct($emitter, $hermesEmitter, $request);
        $this->translator = $translator;
        $this->accessTokensRepository = $accessTokensRepository;
        $this->session = $session;
    }

    public function authenticate()
    {
        $authSession = $this->session->getSection('auth');
        if ($authSession->get(self::SESSION_AUTH_DISABLED)) {
            return false;
        }

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
            throw new AuthenticationException($this->translator->translate('users.authenticator.access_token.invalid_token'), Authenticator::FAILURE);
        }
        $user = $tokenRow->user;
        if (!$user) {
            throw new AuthenticationException($this->translator->translate('users.authenticator.access_token.invalid_token'), Authenticator::IDENTITY_NOT_FOUND);
        }
        if ($user->role === UsersRepository::ROLE_ADMIN) {
            $authSession = $this->session->getSection('auth');
            $authSession->set(self::SESSION_AUTH_DISABLED, true);
            throw new AuthenticationException($this->translator->translate('users.authenticator.access_token.autologin_disabled'), Authenticator::NOT_APPROVED);
        }

        $this->addAttempt($user->email, $user, $this->source, LoginAttemptsRepository::STATUS_ACCESS_TOKEN_OK);
        return $user;
    }
}
