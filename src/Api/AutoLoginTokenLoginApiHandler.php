<?php

namespace Crm\UsersModule\Api;

use Crm\ApiModule\Api\ApiHandler;
use Crm\ApiModule\Api\JsonResponse;
use Crm\ApiModule\Authorization\ApiAuthorizationInterface;
use Crm\ApiModule\Params\InputParam;
use Crm\ApiModule\Params\ParamsProcessor;
use Crm\UsersModule\Auth\UserAuthenticator;
use Crm\UsersModule\Repositories\DeviceTokensRepository;
use Crm\UsersModule\Repository\AccessTokensRepository;
use Nette\Http\Response;
use Nette\Security\AuthenticationException;
use Tracy\Debugger;
use Tracy\ILogger;

class AutoLoginTokenLoginApiHandler extends ApiHandler
{
    private $userAuthenticator;

    private $accessTokensRepository;

    private $deviceTokensRepository;

    public function __construct(
        AccessTokensRepository $accessTokensRepository,
        UserAuthenticator $userAuthenticator,
        DeviceTokensRepository $deviceTokensRepository
    ) {
        $this->accessTokensRepository = $accessTokensRepository;
        $this->userAuthenticator = $userAuthenticator;
        $this->deviceTokensRepository = $deviceTokensRepository;
    }

    public function params(): array
    {
        return [
            new InputParam(InputParam::TYPE_POST, 'autologin_token', InputParam::REQUIRED),
            new InputParam(InputParam::TYPE_POST, 'device_token', InputParam::OPTIONAL),
            new InputParam(InputParam::TYPE_POST, 'source', InputParam::OPTIONAL),
        ];
    }

    /**
     * @param ApiAuthorizationInterface $authorization
     * @return \Nette\Application\Response
     */
    public function handle(ApiAuthorizationInterface $authorization)
    {
        $paramsProcessor = new ParamsProcessor($this->params());
        $params = $paramsProcessor->getValues();

        $deviceToken = null;
        if (isset($params['device_token'])) {
            $deviceToken = $this->deviceTokensRepository->findByToken($params['device_token']);
            if (!$deviceToken) {
                $response = new JsonResponse([
                    'status' => 'error',
                    'error' => 'invalid_device_token',
                    'message' => "device token doesn't exist: ". $params['device_token']
                ]);
                $response->setHttpCode(Response::S404_NOT_FOUND);
                return $response;
            }
        }

        try {
            $source = 'api';
            if (isset($params['source']) && $params['source'] !== 'api') {
                $source .= '+' . $params['source'];
            }
            $identity = $this->userAuthenticator->authenticate([
                'autologin_token' => $params['autologin_token'],
                'source' => $source,
            ]);
        } catch (AuthenticationException $e) {
            $response = new JsonResponse([
                'status' => 'error',
                'error' => 'auth_failed',
                'message' => $e->getMessage()
            ]);
            if ($e->getCode() === UserAuthenticator::NOT_APPROVED) {
                $response->setHttpCode(Response::S403_FORBIDDEN);
            } else {
                $response->setHttpCode(Response::S401_UNAUTHORIZED);
            }
            return $response;
        }

        $result = [
            'status' => 'ok',
            'user' => [
                'id' => $identity->id,
                'email' => $identity->data['email'],
                'confirmed_at' => $identity->data['confirmed_at'] ? $identity->data['confirmed_at']->format(DATE_RFC3339) : null,
                'public_name' => $identity->data['public_name'],
                'first_name' => $identity->data['first_name'],
                'last_name' => $identity->data['last_name'],
            ],
        ];

        if ($identity->getRoles()) {
            $result['user']['roles'] = $identity->getRoles();
        }

        $lastToken = $this->accessTokensRepository->allUserTokens($identity->id)->limit(1)->fetch();
        if (!$lastToken) {
            Debugger::log('Missing access token for user', ILogger::ERROR);
            $response = new JsonResponse([
                'status' => 'error',
                'error' => 'missing_access_token',
                'message' => 'Missing access token for user'
            ]);
            $response->setHttpCode(Response::S500_INTERNAL_SERVER_ERROR);
            return $response;
        }

        if ($deviceToken) {
            $this->accessTokensRepository->pairWithDeviceToken($lastToken, $deviceToken);
        }

        $result['access']['token'] = $lastToken->token;

        $response = new JsonResponse($result);
        $response->setHttpCode(Response::S200_OK);
        return $response;
    }
}
