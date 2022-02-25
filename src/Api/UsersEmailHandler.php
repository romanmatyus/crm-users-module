<?php

namespace Crm\UsersModule\Api;

use Crm\ApiModule\Api\ApiHandler;
use Crm\ApiModule\Api\JsonResponse;
use Crm\ApiModule\Params\InputParam;
use Crm\ApiModule\Params\ParamsProcessor;
use Crm\ApiModule\Response\ApiResponseInterface;
use Crm\UsersModule\Auth\Rate\RateLimitException;
use Crm\UsersModule\Auth\UserAuthenticator;
use Crm\UsersModule\Auth\UserManager;
use Crm\UsersModule\Authenticator\UsersAuthenticator;
use Crm\UsersModule\Email\EmailValidator;
use Nette\Http\IResponse;
use Nette\Security\AuthenticationException;
use Nette\Utils\Validators;

class UsersEmailHandler extends ApiHandler
{
    private UserManager $userManager;

    private EmailValidator $emailValidator;

    private UsersAuthenticator $usersAuthenticator;

    public function __construct(
        UserManager $userManager,
        EmailValidator $emailValidator,
        UsersAuthenticator $usersAuthenticator
    ) {
        $this->userManager = $userManager;
        $this->emailValidator = $emailValidator;
        $this->usersAuthenticator = $usersAuthenticator;
    }

    public function params(): array
    {
        return [
            new InputParam(InputParam::TYPE_POST, 'email', InputParam::REQUIRED),
            new InputParam(InputParam::TYPE_POST, 'password', InputParam::OPTIONAL),
        ];
    }

    public function handle(array $params): ApiResponseInterface
    {
        $paramsProcessor = new ParamsProcessor($this->params());
        $params = $paramsProcessor->getValues();

        $passwordStatus = null;
        $user = $this->userManager->loadUserByEmail($params['email']);
        try {
            if (!$params['email']) {
                 $response = new JsonResponse(['status' => 'error', 'message' => 'No valid email', 'code' => 'email_missing']);
                 $response->setHttpCode(IResponse::S200_OK);
                 return $response;
                // Validate email format only if user email does not exist in our DB, since external services may be slow
            } elseif (!Validators::isEmail($params['email']) || !$this->emailValidator->isValid($params['email'])) {
                $response = new JsonResponse(['status' => 'error', 'message' => 'Invalid email format', 'code' => 'invalid_email']);
                $response->setHttpCode(IResponse::S200_OK);
                return $response;
            }

            $this->usersAuthenticator->setCredentials([
                'username' => $params['email'],
                'password' => $params['password'] ?? ''
            ]);
            $this->usersAuthenticator->authenticate();
            $status = 'taken';
            $passwordStatus = true;
        } catch (RateLimitException $e) {
            $response = new JsonResponse(['status' => 'error', 'message' => 'Rate limit exceeded', 'code' => 'rate_limit_exceeded']);
            $response->setHttpCode(IResponse::S200_OK);
            return $response;
        } catch (AuthenticationException $authException) {
            if ($authException->getCode() === UserAuthenticator::IDENTITY_NOT_FOUND) {
                $status = 'available';
            } elseif ($authException->getCode() === UserAuthenticator::INVALID_CREDENTIAL) {
                $status = 'taken';
                $passwordStatus = ($params['password']) ? false : null;
            } elseif ($authException->getCode() ===  UserAuthenticator::NOT_APPROVED) {
                $user = null;
                $status = 'available';
            } else {
                $status = 'taken';
            }
        }

        $result = [
            'email' => $params['email'],
            'id' => $user->id ?? null,
            'status' => $status,
            'password' => $passwordStatus,
         ];

        $response = new JsonResponse($result);
        $response->setHttpCode(IResponse::S200_OK);
        return $response;
    }
}