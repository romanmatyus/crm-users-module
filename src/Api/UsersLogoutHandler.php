<?php

namespace Crm\UsersModule\Api;

use Crm\ApiModule\Api\ApiHandler;
use Crm\ApiModule\Api\JsonResponse;
use Crm\ApiModule\Response\ApiResponseInterface;
use Crm\UsersModule\Auth\AccessTokensApiAuthorizationInterface;
use Crm\UsersModule\Events\UserSignOutEvent;
use Crm\UsersModule\Repository\AccessTokensRepository;
use League\Event\Emitter;
use Nette\Http\Response;

class UsersLogoutHandler extends ApiHandler
{
    private $accessTokensRepository;

    private $emitter;

    public function __construct(
        AccessTokensRepository $accessTokensRepository,
        Emitter $emitter
    ) {
        $this->accessTokensRepository = $accessTokensRepository;
        $this->emitter = $emitter;
    }

    public function params(): array
    {
        return [];
    }

    public function handle(array $params): ApiResponseInterface
    {
        $authorization = $this->getAuthorization();
        if (!($authorization instanceof AccessTokensApiAuthorizationInterface)) {
            throw new \Exception("Wrong authorization service used. Should be 'AccessTokensApiAuthorizationInterface'");
        }

        $loggedOutUsers = [];
        foreach ($authorization->getAccessTokens() as $accessToken) {
            $loggedOutUsers[$accessToken->user_id] = $accessToken->user;
            $this->accessTokensRepository->remove($accessToken->token);
        }
        foreach ($loggedOutUsers as $user) {
            $this->emitter->emit(new UserSignOutEvent($user));
        }

        $response = new JsonResponse(['status' => 'ok']);
        $response->setHttpCode(Response::S200_OK);
        return $response;
    }
}
