<?php

namespace Crm\UsersModule\Api;

use Crm\ApiModule\Api\ApiHandler;
use Crm\ApiModule\Params\InputParam;
use Crm\ApiModule\Params\ParamsProcessor;
use Crm\UsersModule\Repository\UsersRepository;
use Crm\UsersModule\User\UnclaimedUser;
use Nette\Http\IResponse;
use Nette\Http\Request;
use Nette\Utils\Validators;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;

class EmailValidationApiHandler extends ApiHandler
{
    private $request;

    private $usersRepository;

    private $action = 'validate';
    private UnclaimedUser $unclaimedUser;

    public function __construct(
        Request $request,
        UsersRepository $usersRepository,
        UnclaimedUser $unclaimedUser
    ) {
        $this->request = $request;
        $this->usersRepository = $usersRepository;
        $this->unclaimedUser = $unclaimedUser;
    }

    public function params(): array
    {
        return [
            new InputParam(InputParam::TYPE_POST, 'email', InputParam::REQUIRED),
        ];
    }

    public function setAction(string $action)
    {
        $this->action = $action;
    }


    public function handle(array $params): ResponseInterface
    {
        $paramsProcessor = new ParamsProcessor($this->params());

        $error = $paramsProcessor->hasError();
        if ($error) {
            $response = new JsonApiResponse(IResponse::S400_BAD_REQUEST, [
                'status' => 'error',
                'message' => $error,
                'code' => 'invalid_request',
            ]);
            return $response;
        }

        $params = $paramsProcessor->getValues();
        if (!Validators::isEmail($params['email'])) {
            $response = new JsonApiResponse(IResponse::S400_BAD_REQUEST, [
                'status' => 'error',
                'message' => 'Email is not valid',
                'code' => 'invalid_param',
            ]);
            return $response;
        }

        $user = $this->usersRepository->getByEmail($params['email']);
        if (!$user || $this->unclaimedUser->isUnclaimedUser($user)) {
            $result = [
                'status'  => 'error',
                'message' => 'Email isn\'t assigned to any user',
                'code'    => 'email_not_found',
            ];
            $response = new JsonApiResponse(IResponse::S404_NOT_FOUND, $result);
            return $response;
        }

        $action = $this->getAction();
        if ($action === 'validate') {
            $this->usersRepository->setEmailValidated($user, new \DateTime());
            $message = 'Email has been validated';
        } elseif ($action === 'invalidate') {
            $this->usersRepository->setEmailInvalidated($user);
            $message = 'Email has been invalidated';
        } else {
            throw new \Exception('invalid action resolved: ' . $action);
        }

        $result = [
            'status'  => 'ok',
            'message' => $message,
            'code'    => 'success',
        ];

        $response = new JsonApiResponse(IResponse::S200_OK, $result);

        return $response;
    }

    private function getAction(): string
    {
        if (isset($this->action)) {
            return $this->action;
        }
        if (strpos($this->request->getUrl()->getPath(), "invalidate") !== false) {
            return 'invalidate';
        }
        return 'validate';
    }
}
