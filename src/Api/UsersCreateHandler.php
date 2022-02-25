<?php

namespace Crm\UsersModule\Api;

use Crm\ApiModule\Api\ApiHandler;
use Crm\ApiModule\Api\JsonResponse;
use Crm\ApiModule\Authorization\NoAuthorization;
use Crm\ApiModule\Params\InputParam;
use Crm\ApiModule\Response\ApiResponseInterface;
use Crm\ApplicationModule\Request;
use Crm\UsersModule\Auth\InvalidEmailException;
use Crm\UsersModule\Auth\Rate\RegistrationIpRateLimit;
use Crm\UsersModule\Auth\UserManager;
use Crm\UsersModule\Repositories\DeviceTokensRepository;
use Crm\UsersModule\Repository\AccessTokensRepository;
use Crm\UsersModule\Repository\RegistrationAttemptsRepository;
use Crm\UsersModule\Repository\UserAlreadyExistsException;
use Crm\UsersModule\Repository\UsersRepository;
use Crm\UsersModule\User\UnclaimedUser;
use Nette\Database\Table\ActiveRow;
use Nette\Http\Response;
use Nette\Utils\Validators;

class UsersCreateHandler extends ApiHandler
{
    private UserManager $userManager;
    private AccessTokensRepository $accessTokensRepository;
    private DeviceTokensRepository $deviceTokensRepository;
    private UsersRepository $usersRepository;
    private UnclaimedUser $unclaimedUser;
    private RegistrationIpRateLimit $registrationIpRateLimit;
    private RegistrationAttemptsRepository $registrationAttemptsRepository;

    public function __construct(
        UserManager $userManager,
        AccessTokensRepository $accessTokensRepository,
        DeviceTokensRepository $deviceTokensRepository,
        UsersRepository $usersRepository,
        UnclaimedUser $unclaimedUser,
        RegistrationIpRateLimit $registrationIpRateLimit,
        RegistrationAttemptsRepository $registrationAttemptsRepository
    ) {
        $this->userManager = $userManager;
        $this->accessTokensRepository = $accessTokensRepository;
        $this->deviceTokensRepository = $deviceTokensRepository;
        $this->usersRepository = $usersRepository;
        $this->unclaimedUser = $unclaimedUser;
        $this->registrationIpRateLimit = $registrationIpRateLimit;
        $this->registrationAttemptsRepository = $registrationAttemptsRepository;
    }

    public function params(): array
    {
        return [
            new InputParam(InputParam::TYPE_POST, 'email', InputParam::REQUIRED),
            new InputParam(InputParam::TYPE_POST, 'password', InputParam::OPTIONAL),
            new InputParam(InputParam::TYPE_POST, 'first_name', InputParam::OPTIONAL),
            new InputParam(InputParam::TYPE_POST, 'last_name', InputParam::OPTIONAL),
            new InputParam(InputParam::TYPE_POST, 'ext_id', InputParam::OPTIONAL),
            new InputParam(InputParam::TYPE_POST, 'source', InputParam::OPTIONAL),
            new InputParam(InputParam::TYPE_POST, 'referer', InputParam::OPTIONAL),
            new InputParam(InputParam::TYPE_POST, 'note', InputParam::OPTIONAL),
            new InputParam(InputParam::TYPE_POST, 'send_email', InputParam::OPTIONAL),
            new InputParam(InputParam::TYPE_POST, 'disable_email_validation', InputParam::OPTIONAL),
            new InputParam(InputParam::TYPE_POST, 'device_token', InputParam::OPTIONAL),
            new InputParam(InputParam::TYPE_POST, 'unclaimed', InputParam::OPTIONAL),
        ];
    }

    public function handle(array $params): ApiResponseInterface
    {
        $authorization = $this->getAuthorization();

        if (!isset($params['source']) && isset($_GET['source'])) {
            $params['source'] = $_GET['source'];
        }

        if ($authorization instanceof NoAuthorization) {
            if ($this->registrationIpRateLimit->reachLimit(Request::getIp())) {
                $this->addAttempt($params['email'], null, $params['source'], RegistrationAttemptsRepository::STATUS_RATE_LIMIT_EXCEEDED);

                $response = new JsonResponse(['status' => 'error', 'message' => 'Limit reached', 'code' => 'limit_reached']);
                $response->setHttpCode(Response::S429_TOO_MANY_REQUESTS);
                return $response;
            }
        }

        $email = $params['email'];
        if (!$email) {
            $this->addAttempt($params['email'], null, $params['source'], RegistrationAttemptsRepository::STATUS_INVALID_EMAIL);
            $response = new JsonResponse(['status' => 'error', 'message' => 'Invalid email', 'code' => 'invalid_email']);
            $response->setHttpCode(Response::S404_NOT_FOUND);
            return $response;
        }
        if (!Validators::isEmail($email)) {
            $this->addAttempt($params['email'], null, $params['source'], RegistrationAttemptsRepository::STATUS_INVALID_EMAIL);
            $response = new JsonResponse(['status' => 'error', 'message' => 'Invalid email', 'code' => 'invalid_email']);
            $response->setHttpCode(Response::S404_NOT_FOUND);
            return $response;
        }

        $unclaimed = filter_var($params['unclaimed'], FILTER_VALIDATE_BOOLEAN);
        $user = $this->userManager->loadUserByEmail($email) ?: null;

        // if user found allow only unclaimed user to get registered
        if ($user && ($unclaimed || !$this->unclaimedUser->isUnclaimedUser($user))) {
            $this->addAttempt($params['email'], null, $params['source'], RegistrationAttemptsRepository::STATUS_TAKEN_EMAIL);
            $response = new JsonResponse(['status' => 'error', 'message' => 'Email is already taken', 'code' => 'email_taken']);
            $response->setHttpCode(Response::S404_NOT_FOUND);
            return $response;
        }

        $source = 'api';
        if ($params['source'] && strlen($params['source']) > 0) {
            $source = $params['source'];
        }

        $referer = null;
        if (isset($params['referer']) && $params['referer']) {
            $referer = $params['referer'];
        }

        $sendEmail = true;
        if ($params['send_email']) {
            $sendEmail = filter_var($params['send_email'], FILTER_VALIDATE_BOOLEAN);
        }

        $checkEmail = true;
        if (isset($params['disable_email_validation']) && ($params['disable_email_validation'] == '1' || $params['disable_email_validation'] == 'true')) {
            $checkEmail = false;
        }

        $deviceToken = null;
        if (!empty($params['device_token'])) {
            $deviceToken = $this->deviceTokensRepository->findByToken($params['device_token']);
            if (!$deviceToken) {
                $this->addAttempt($params['email'], $user, $params['source'], RegistrationAttemptsRepository::STATUS_DEVICE_TOKEN_NOT_FOUND);
                $response = new JsonResponse([
                    'status' => 'error',
                    'message' => 'Device token doesn\'t exist',
                    'code' => 'device_token_doesnt_exist'
                ]);
                $response->setHttpCode(Response::S400_BAD_REQUEST);
                return $response;
            }
        }

        $password = $params['password'] ?? null;

        try {
            if ($user) {
                $user = $this->unclaimedUser->makeUnclaimedUserRegistered($user, $sendEmail, $source, $referer, $checkEmail, $password, $deviceToken);
            } elseif ($unclaimed) {
                $user = $this->unclaimedUser->createUnclaimedUser($email, $source);
            } else {
                $user = $this->userManager->addNewUser($email, $sendEmail, $source, $referer, $checkEmail, $password);
            }
        } catch (InvalidEmailException $e) {
            $this->addAttempt($params['email'], $user, $params['source'], RegistrationAttemptsRepository::STATUS_INVALID_EMAIL);
            $response = new JsonResponse(['status' => 'error', 'message' => 'Invalid email', 'code' => 'invalid_email']);
            $response->setHttpCode(Response::S404_NOT_FOUND);
            return $response;
        } catch (UserAlreadyExistsException $e) {
            $this->addAttempt($params['email'], $user, $params['source'], RegistrationAttemptsRepository::STATUS_TAKEN_EMAIL);
            $response = new JsonResponse(['status' => 'error', 'message' => 'Email is already taken', 'code' => 'email_taken']);
            $response->setHttpCode(Response::S404_NOT_FOUND);
            return $response;
        }

        $userData = [];
        if (!empty($params['first_name'])) {
            $userData['first_name'] = $params['first_name'];
        }

        if (!empty($params['last_name'])) {
            $userData['last_name'] = $params['last_name'];
        }

        if (!empty($params['ext_id'])) {
            $userData['ext_id'] = (int)$params['ext_id'];
        }

        if (!empty($params['note'])) {
            $userData['note'] = $params['note'];
        }

        $this->usersRepository->update($user, $userData);

        $lastToken = $this->accessTokensRepository->allUserTokens($user->id)->limit(1)->fetch() ?: null;
        if ($lastToken && $deviceToken) {
            $this->accessTokensRepository->pairWithDeviceToken($lastToken, $deviceToken);
        }

        $this->addAttempt($params['email'], $user, $params['source'], RegistrationAttemptsRepository::STATUS_OK);
        $result = $this->formatResponse($user, $lastToken);

        $response = new JsonResponse($result);
        $response->setHttpCode(Response::S200_OK);
        return $response;
    }

    private function formatResponse(ActiveRow $user, ?ActiveRow $lastToken): array
    {
        $user = $this->usersRepository->find($user->id);
        $result = [
            'status' => 'ok',
            'user' => [
                'id' => $user->id,
                'email' => $user->email,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'confirmed_at' => $user->confirmed_at ? $user->confirmed_at->format(DATE_RFC3339) : null,
            ],
        ];

        if ($user->ext_id) {
            $result['user']['ext_id'] = $user->ext_id;
        }

        if ($lastToken) {
            $result['access']['token'] = $lastToken->token;
        }
        return $result;
    }

    private function addAttempt($email, $user, $source, $status): void
    {
        $this->registrationAttemptsRepository->insertAttempt(
            $email,
            $user,
            $source,
            $status,
            Request::getIp(),
            Request::getUserAgent(),
            new \DateTime()
        );
    }
}
