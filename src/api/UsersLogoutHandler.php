<?php

namespace Crm\UsersModule\Api;

use Crm\ApiModule\Api\ApiHandler;
use Crm\ApiModule\Api\JsonResponse;
use Crm\ApiModule\Authorization\ApiAuthorizationInterface;
use Crm\UsersModule\Auth\AccessTokensApiAuthorizationInterface;
use Crm\UsersModule\Repository\AccessTokensRepository;
use Nette\Http\Response;

class UsersLogoutHandler extends ApiHandler
{
    private $accessTokensRepository;

    public function __construct(
        AccessTokensRepository $accessTokensRepository
    ) {
        $this->accessTokensRepository = $accessTokensRepository;
    }

    public function params()
    {
        return [];
    }

    public function handle(ApiAuthorizationInterface $authorization)
    {
        if (!($authorization instanceof AccessTokensApiAuthorizationInterface)) {
            throw new \Exception("Wrong authorization service used. Should be 'AccessTokensApiAuthorizationInterface'");
        }

        foreach ($authorization->getAccessTokens() as $accessToken) {
            $this->accessTokensRepository->remove($accessToken);
        }

        $response = new JsonResponse(['status' => 'ok']);
        $response->setHttpCode(Response::S200_OK);
        return $response;
    }
}
