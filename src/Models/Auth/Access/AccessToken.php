<?php

namespace Crm\UsersModule\Auth\Access;

use Crm\ApplicationModule\Access\AccessManager;
use Crm\ApplicationModule\Request as CrmRequest;
use Crm\UsersModule\Repository\AccessTokensRepository;
use Crm\UsersModule\Repository\UsersRepository;
use League\Event\Emitter;
use Nette\Http\IRequest;
use Nette\Http\Request;
use Nette\Http\Response;

class AccessToken
{
    private $version = 3;

    private $accessTokenRepository;

    private $usersRepository;

    private $accessManager;

    private $emitter;

    protected $cookieName = 'n_token';
    
    private $sameSiteFlag = 'Lax';

    public function __construct(
        AccessTokensRepository $accessTokensRepository,
        UsersRepository $usersRepository,
        AccessManager $accessManager,
        Emitter $emitter
    ) {
        $this->accessTokenRepository = $accessTokensRepository;
        $this->usersRepository = $usersRepository;
        $this->accessManager = $accessManager;
        $this->emitter = $emitter;
    }

    /**
     * Optionally, one can modify `SameSite` flag sent when setting n_token cookie
     * By default, 'Lax' value is used
     * @param string $sameSiteFlag
     */
    public function setSameSiteFlag(string $sameSiteFlag): void
    {
        $this->sameSiteFlag = $sameSiteFlag;
    }

    public function addUserToken($user, Request $request = null, Response $response = null, ?string $source = null)
    {
        $userRow = $this->usersRepository->find($user->id);

        // remove old token if exists
        if ($request) {
            $cookieToken = $request->getCookie($this->cookieName);
            $token = $this->accessTokenRepository->loadToken($cookieToken);
            if ($token && $token->user_id == $userRow->id) {
                $this->accessTokenRepository->remove($token->token);
            }
        }

        $token = $this->accessTokenRepository->add($userRow, $this->version, $source);

        if ($response && !CrmRequest::isApi()) {
            $response->setCookie(
                $this->cookieName,
                $token->token,
                strtotime('+10 years'),
                '/',
                CrmRequest::getDomain(),
                null,
                false,
                $this->sameSiteFlag
            );

            $response->setCookie(
                'n_version',
                $this->version,
                strtotime('+10 years'),
                '/',
                CrmRequest::getDomain(),
                null,
                false,
                $this->sameSiteFlag
            );
        }

        return $userRow;
    }

    public function deleteActualUserToken($user, Request $request, Response $response)
    {
        $cookieToken = $request->getCookie($this->cookieName);
        $token = $this->accessTokenRepository->loadToken($cookieToken);
        if ($token && $token->user_id == $user->id) {
            $this->accessTokenRepository->remove($token->token);
        }

        $response->deleteCookie($this->cookieName, '/', CrmRequest::getDomain());
        $response->deleteCookie('n_version', '/', CrmRequest::getDomain());
    }

    public function getToken(IRequest $request)
    {
        return $request->getCookie($this->cookieName);
    }

    public function lastVersion()
    {
        return $this->version;
    }
}
