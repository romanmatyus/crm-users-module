<?php

namespace Crm\UsersModule\Api;

use Crm\ApiModule\Api\ApiHandler;
use Crm\ApiModule\Api\JsonResponse;
use Crm\ApiModule\Api\JsonValidationTrait;
use Crm\ApiModule\Authorization\ApiAuthorizationInterface;
use Crm\UsersModule\Repository\UserMetaRepository;
use Crm\UsersModule\Repository\UsersRepository;
use Nette\Http\Response;

class UserMetaDeleteHandler extends ApiHandler
{
    use JsonValidationTrait;

    private $userMetaRepository;

    private $usersRepository;

    public function __construct(
        UserMetaRepository $userMetaRepository,
        UsersRepository $usersRepository
    ) {
        $this->userMetaRepository = $userMetaRepository;
        $this->usersRepository = $usersRepository;
    }

    public function params()
    {
        return [];
    }

    public function handle(ApiAuthorizationInterface $authorization): ?JsonResponse
    {
        $result = $this->validateInput(__DIR__ . '/user-meta-delete.schema.json');
        if ($result->hasErrorResponse()) {
            return $result->getErrorResponse();
        }

        $json = $result->getParsedObject();

        $userId = $json->user_id;
        $key = $json->key;
        $value = $json->value ?? null;

        $userRow = $this->usersRepository->find($userId);

        if (!$userRow) {
            $response = new JsonResponse([
                'status' => 'error',
                'message' => "User not found: {$userId}",
            ]);
            $response->setHttpCode(Response::S404_NOT_FOUND);

            return $response;
        }

        $this->userMetaRepository->removeMeta($userId, $key, $value);

        $response = new JsonResponse(null);
        $response->setHttpCode(Response::S204_NO_CONTENT);

        return $response;
    }
}
