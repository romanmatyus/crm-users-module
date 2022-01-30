<?php

namespace Crm\UsersModule\Api;

use Crm\ApiModule\Api\ApiHandler;
use Crm\ApiModule\Api\JsonResponse;
use Crm\ApiModule\Api\JsonValidationTrait;
use Crm\ApiModule\Authorization\ApiAuthorizationInterface;
use Crm\UsersModule\Repository\UserMetaRepository;
use Crm\UsersModule\Repository\UsersRepository;
use Nette\Http\Response;

class UserMetaUpsertHandler extends ApiHandler
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

    public function handle(ApiAuthorizationInterface $authorization)
    {
        $result = $this->validateInput(__DIR__ . '/user-meta-upsert.schema.json');
        if ($result->hasErrorResponse()) {
            return $result->getErrorResponse();
        }

        $json = $result->getParsedObject();

        $userId = $json->user_id;
        $key = $json->key;
        $value = $json->value;
        $isPublic = $json->is_public ?? false;

        $userRow = $this->usersRepository->find($userId);

        if (!$userRow) {
            $response = new JsonResponse([
                'status' => 'error',
                'message' => "User not found: {$userId}",
            ]);
            $response->setHttpCode(Response::S404_NOT_FOUND);

            return $response;
        }

        $userMetaRow = $this->userMetaRepository->add($userRow, $key, $value, null, $isPublic);

        $response = new JsonResponse([
            'key' => $userMetaRow->key,
            'value' => $userMetaRow->value,
            'is_public' => (bool)$userMetaRow->is_public
        ]);
        $response->setHttpCode(Response::S200_OK);

        return $response;
    }
}
