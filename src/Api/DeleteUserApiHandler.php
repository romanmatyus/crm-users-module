<?php

namespace Crm\UsersModule\Api;

use Crm\ApiModule\Api\ApiHandler;
use Crm\ApiModule\Api\EmptyResponse;
use Crm\ApiModule\Api\JsonResponse;
use Crm\ApiModule\Response\ApiResponseInterface;
use Crm\ApplicationModule\User\DeleteUserData;
use Crm\UsersModule\Auth\UsersApiAuthorizationInterface;
use Kdyby\Translation\Translator;
use Nette\Http\IResponse;

class DeleteUserApiHandler extends ApiHandler
{
    private $deleteUserData;

    private $translator;

    public function __construct(
        DeleteUserData $deleteUserData,
        Translator  $translator
    ) {
        $this->deleteUserData = $deleteUserData;
        $this->translator = $translator;
    }

    public function params(): array
    {
        return [];
    }

    public function handle(array $params): ApiResponseInterface
    {
        if (!($authorization instanceof UsersApiAuthorizationInterface)) {
            throw new \Exception("Wrong authorization service used. Should be 'UsersApiAuthorizationInterface'");
        }

        $authorizedUsers = $authorization->getAuthorizedUsers();
        $authorizedUser = reset($authorizedUsers);
        [$canBeDeleted, $errors] = $this->deleteUserData->canBeDeleted($authorizedUser->id);
        if (!$canBeDeleted) {
            $response = new JsonResponse([
                'status' => "error",
                'code' => "user_delete_protected",
                'message' => 'Unable to delete user due to system protection configuration',
                'reason' => $this->translator->translate('users.frontend.settings.account_delete.cannot_delete'),
            ]);
            $response->setHttpCode(IResponse::S403_FORBIDDEN);
            return $response;
        }

        $this->deleteUserData->deleteData($authorizedUser->id);
        $response = new EmptyResponse();
        $response->setHttpCode(IResponse::S204_NO_CONTENT);
        return $response;
    }
}
