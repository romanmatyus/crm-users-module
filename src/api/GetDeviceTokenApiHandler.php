<?php

namespace Crm\UsersModule\Api;

use Crm\ApiModule\Api\ApiHandler;
use Crm\ApiModule\Api\JsonResponse;
use Crm\ApiModule\Authorization\ApiAuthorizationInterface;
use Crm\ApiModule\Params\InputParam;
use Crm\ApiModule\Params\ParamsProcessor;
use Crm\UsersModule\Repositories\DeviceTokensRepository;
use Nette\Http\Response;

class GetDeviceTokenApiHandler extends ApiHandler
{
    private $deviceTokensRepository;

    public function __construct(DeviceTokensRepository $deviceTokensRepository)
    {
        $this->deviceTokensRepository = $deviceTokensRepository;
    }

    public function params()
    {
        return [
            new InputParam(InputParam::TYPE_POST, 'device_id', InputParam::REQUIRED),
        ];
    }

    public function handle(ApiAuthorizationInterface $authorization)
    {
        $paramsProcessor = new ParamsProcessor($this->params());
        $error = $paramsProcessor->isError();
        if ($error) {
            $response = new JsonResponse([
                'status' => 'error',
                'message' => 'Wrong input - ' . $error
            ]);
            $response->setHttpCode(Response::S400_BAD_REQUEST);
            return $response;
        }

        $params = $paramsProcessor->getValues();

        $token = $this->deviceTokensRepository->add($params['device_id']);
        $response = new JsonResponse([
            'device_token' => $token->token
        ]);
        $response->setHttpCode(Response::S200_OK);
        return $response;
    }
}
