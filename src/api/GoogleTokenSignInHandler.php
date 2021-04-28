<?php

namespace Crm\UsersModule\Api;

use Crm\ApiModule\Api\ApiHandler;
use Crm\ApiModule\Api\JsonResponse;
use Crm\ApiModule\Authorization\ApiAuthorizationInterface;
use Crm\ApiModule\Params\InputParam;
use Crm\ApiModule\Params\ParamsProcessor;
use Crm\UsersModule\Auth\Sso\GoogleSignIn;
use Crm\UsersModule\Repository\AccessTokensRepository;
use Nette\Database\Table\IRow;
use Nette\Http\Response;

/**
 * Implements validation of Google Token ID
 * see: https://developers.google.com/identity/sign-in/web/backend-auth
 *
 * @package Crm\UsersModule\Api
 */
class GoogleTokenSignInHandler extends ApiHandler
{
    private $googleSignIn;

    private $accessTokensRepository;

    public function __construct(
        GoogleSignIn $googleSignIn,
        AccessTokensRepository $accessTokensRepository
    ) {
        $this->googleSignIn = $googleSignIn;
        $this->accessTokensRepository = $accessTokensRepository;
    }

    public function params()
    {
        return [
            new InputParam(InputParam::TYPE_POST, 'id_token', InputParam::REQUIRED),
            new InputParam(InputParam::TYPE_POST, 'create_access_token', InputParam::OPTIONAL),
        ];
    }

    public function handle(ApiAuthorizationInterface $authorization): ?JsonResponse
    {
        $paramsProcessor = new ParamsProcessor($this->params());
        $error = $paramsProcessor->isError();
        if ($error) {
            $response = new JsonResponse([
                'status' => 'error',
                'code' => 'invalid_id_token',
                'message' => 'Wrong input - ' . $error
            ]);
            $response->setHttpCode(Response::S400_BAD_REQUEST);
            return $response;
        }
        $params = $paramsProcessor->getValues();
        $idToken = $params['id_token'];
        $createAccessToken = filter_var($params['create_access_token'], FILTER_VALIDATE_BOOLEAN) ?? false;

        $user = $this->googleSignIn->signInUsingIdToken($idToken);

        if (!$user) {
            $response = new JsonResponse([
                'status' => 'error',
                'code' => 'error_verifying_id_token',
                'message' => 'Unable to verify ID token',
            ]);
            $response->setHttpCode(Response::S400_BAD_REQUEST);
            return $response;
        }

        $accessToken = null;
        if ($createAccessToken) {
            $accessToken = $this->accessTokensRepository->add($user, 3, GoogleSignIn::ACCESS_TOKEN_SOURCE_WEB_GOOGLE_SSO);
        }

        $result = $this->formatResponse($user, $accessToken);
        $response = new JsonResponse($result);
        $response->setHttpCode(Response::S200_OK);
        return $response;
    }

    private function formatResponse(IRow $user, ?IRow $accessToken): array
    {
        $result = [
            'status' => 'ok',
            'user' => [
                'id' => $user->id,
                'email' => $user->email,
                'created_at' => $user->created_at->format(\DateTimeInterface::RFC3339)
            ],
        ];

        if ($accessToken) {
            $result['access']['token'] = $accessToken->token;
        }
        return $result;
    }
}
