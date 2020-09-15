<?php

namespace Crm\UsersModule\Api;

use Crm\ApiModule\Api\ApiHandler;
use Crm\ApiModule\Api\JsonResponse;
use Crm\ApiModule\Authorization\ApiAuthorizationInterface;
use Crm\ApiModule\Params\InputParam;
use Crm\ApiModule\Params\ParamsProcessor;
use Crm\UsersModule\Auth\UserAuthenticator;
use Crm\UsersModule\Repository\AccessTokensRepository;
use Nette\Http\Response;
use Nette\Security\AuthenticationException;
use Tracy\Debugger;
use Tracy\ILogger;

class AutoLoginTokenLoginApiHandler extends ApiHandler
{
    private $userAuthenticator;

    private $accessTokensRepository;

    public function __construct(
        AccessTokensRepository $accessTokensRepository,
        UserAuthenticator $userAuthenticator
    ) {
        $this->accessTokensRepository = $accessTokensRepository;
        $this->userAuthenticator = $userAuthenticator;
    }

    public function params()
    {
        return [
            new InputParam(InputParam::TYPE_POST, 'autologin_token', InputParam::REQUIRED),
            new InputParam(InputParam::TYPE_POST, 'source', InputParam::OPTIONAL),
        ];
    }

    /**
     * @param ApiAuthorizationInterface $authorization
     * @return \Nette\Application\IResponse
     */
    public function handle(ApiAuthorizationInterface $authorization)
    {
        $paramsProcessor = new ParamsProcessor($this->params());
        $params = $paramsProcessor->getValues();

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

        $result['access']['token'] = $lastToken->token;

        $response = new JsonResponse($result);
        $response->setHttpCode(Response::S200_OK);
        return $response;
    }
}
