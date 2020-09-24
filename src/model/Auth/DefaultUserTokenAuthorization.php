<?php

namespace Crm\UsersModule\Auth;

use Crm\ApiModule\Authorization\TokenParser;
use Crm\ApplicationModule\Request;
use Crm\UsersModule\Events\UserLastAccessEvent;
use Crm\UsersModule\Repository\AccessTokensRepository;
use DateTime;
use League\Event\Emitter;
use Nette\Security\IAuthorizator;

class DefaultUserTokenAuthorization implements UsersApiAuthorizationInterface, AccessTokensApiAuthorizationInterface
{
    protected $accessTokensRepository;

    protected $emitter;

    protected $errorMessage = false;

    protected $authorizedData = [];

    protected $authorizedUsers = [];

    protected $accessTokens = [];

    public function __construct(
        AccessTokensRepository $accessTokensRepository,
        Emitter $emitter
    ) {
        $this->accessTokensRepository = $accessTokensRepository;
        $this->emitter = $emitter;
    }

    public function authorized($resource = IAuthorizator::ALL)
    {
        $tokenParser = new TokenParser();
        if (!$tokenParser->isOk()) {
            $this->errorMessage = $tokenParser->errorMessage();
            return false;
        }

        $token = $this->accessTokensRepository->loadToken($tokenParser->getToken());

        if (!$token) {
            $this->errorMessage = "Token doesn't exists";
            return false;
        }

        $source = isset($_GET['source']) ? 'api+' . $_GET['source'] : null;
        $accessDate = new DateTime();
        $this->accessTokensRepository->update($token, ['last_used_at' => $accessDate]);
        $this->emitter->emit(new UserLastAccessEvent(
            $token->user,
            $accessDate,
            $source,
            Request::getUserAgent()
        ));

        $this->accessTokens[] = $token;
        $this->authorizedUsers[$token->user_id] = $token->user;
        $this->authorizedData['token'] = $token;
        return true;
    }

    public function getErrorMessage()
    {
        return $this->errorMessage;
    }

    public function getAuthorizedData()
    {
        return $this->authorizedData;
    }

    public function getAuthorizedUsers()
    {
        return $this->authorizedUsers;
    }

    public function getAccessTokens()
    {
        return $this->accessTokens;
    }
}
