<?php

namespace Crm\UsersModule\Api;

use Crm\ApiModule\Api\ApiHandler;
use Crm\ApiModule\Api\JsonResponse;
use Crm\ApiModule\Api\JsonValidationTrait;
use Crm\ApiModule\Authorization\ApiAuthorizationInterface;
use Crm\UsersModule\Repository\UserMetaRepository;
use Crm\UsersModule\Repository\UsersRepository;
use Nette\Http\Response;

class UserMetaListHandler extends ApiHandler
{
    use JsonValidationTrait;

    private $userRepository;

    private $userMetaRepository;

    public function __construct(
        UsersRepository $userRepository,
        UserMetaRepository $userMetaRepository
    ) {
        $this->userRepository = $userRepository;
        $this->userMetaRepository = $userMetaRepository;
    }

    public function params()
    {
        return [];
    }

    public function handle(ApiAuthorizationInterface $authorization)
    {
        $result = $this->validateInput(__DIR__ . '/user-meta-list.schema.json');
        if ($result->hasErrorResponse()) {
            return $result->getErrorResponse();
        }

        $json = $result->getParsedObject();

        $userId = $json->user_id;
        $key = $json->key ?? null;

        $userRow = $this->userRepository->find($userId);

        if (!$userRow) {
            $response = new JsonResponse([
                'status' => 'error',
                'message' => "User not found: {$userId}",
            ]);
            $response->setHttpCode(Response::S404_NOT_FOUND);

            return $response;
        }

        $userMetaRows = $this->userMetaRepository->userMetaRows($userRow);
        if ($key !== null) {
            $userMetaRows->where('key = ?', $key);
        }

        $meta = array_map(function ($data) {
                return [
                    'key' => $data->key,
                    'value' => $data->value,
                    'is_public' => (bool)$data->is_public,
                ];
        }, array_values($userMetaRows->fetchAll()));

        $response = new JsonResponse($meta);
        $response->setHttpCode(Response::S200_OK);

        return $response;
    }
}
