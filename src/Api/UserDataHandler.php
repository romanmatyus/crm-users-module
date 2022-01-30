<?php

namespace Crm\UsersModule\Api;

use Crm\ApiModule\Api\ApiHandler;
use Crm\ApiModule\Api\JsonResponse;
use Crm\ApiModule\Authorization\ApiAuthorizationInterface;
use Crm\ApiModule\Authorization\TokenParser;
use Crm\UsersModule\User\UserData;
use Nette\Http\Response;

class UserDataHandler extends ApiHandler
{
    private $userData;

    private $errorMessage;

    public function __construct(
        UserData $userData
    ) {
        $this->userData = $userData;
    }

    public function params()
    {
        return [];
    }

    public function handle(ApiAuthorizationInterface $authorization)
    {
        $tokenParser = new TokenParser();
        if (!$tokenParser->isOk()) {
            $this->errorMessage = $tokenParser->errorMessage();
            $response = new JsonResponse(['status' => 'error', 'message' => $tokenParser->errorMessage()]);
            $response->setHttpCode(Response::S400_BAD_REQUEST);
            return $response;
        }

        $result = $this->userData->getUserToken($tokenParser->getToken());

        if (!$result) {
            $response = new JsonResponse(['status' => 'error', 'message' => 'Token not found']);
            $response->setHttpCode(Response::S404_NOT_FOUND);
            return $response;
        }

        $response = new JsonResponse($result);
        $response->setHttpCode(Response::S200_OK);
        return $response;
    }
}
