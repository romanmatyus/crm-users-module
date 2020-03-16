<?php

namespace Crm\UsersModule\Api;

use Crm\ApiModule\Api\ApiHandler;
use Crm\ApiModule\Api\JsonResponse;
use Crm\ApiModule\Api\JsonValidationTrait;
use Crm\ApiModule\Authorization\ApiAuthorizationInterface;
use Crm\UsersModule\Repository\UserMetaRepository;
use Nette\Http\Response;

class UserMetaKeyUsersHandler extends ApiHandler
{
    use JsonValidationTrait;

    private $userMetaRepository;

    public function __construct(UserMetaRepository $userMetaRepository)
    {
        $this->userMetaRepository = $userMetaRepository;
    }

    public function params()
    {
        return [];
    }

    public function handle(ApiAuthorizationInterface $authorization)
    {
        $result = $this->validateInput(__DIR__ . '/user-meta-key-users.schema.json');
        if ($result->hasErrorResponse()) {
            return $result->getErrorResponse();
        }

        $json = $result->getParsedObject();

        $key = $json->key;
        $value = $json->value ?? null;

        $userMetaSelection = $this->userMetaRepository->usersWithKey($key, $value);
        $users = array_map(function ($data) {
                return [
                    'user_id' => $data->user_id,
                    'value' => $data->value,
                ];
        }, array_values($userMetaSelection->fetchAll()));

        $response = new JsonResponse($users);
        $response->setHttpCode(Response::S200_OK);

        return $response;
    }
}
