<?php

namespace Crm\UsersModule\Tests;

use Crm\ApiModule\Api\JsonResponse;
use Crm\UsersModule\Api\GetDeviceTokenApiHandler;
use Crm\UsersModule\Api\UsersLogoutHandler;
use Crm\UsersModule\Auth\UserManager;
use Crm\UsersModule\Auth\UserTokenAuthorization;
use Crm\UsersModule\Repositories\DeviceTokensRepository;
use Crm\UsersModule\Repository\AccessTokensRepository;
use Crm\UsersModule\User\UnclaimedUser;
use Nette\Http\Response;

class UsersLogoutHandlerTest extends BaseTestCase
{
    /** @var UsersLogoutHandler */
    private $logoutHandler;

    /** @var GetDeviceTokenApiHandler */
    private $getDeviceTokenApiHandler;

    /** @var AccessTokensRepository */
    private $accessTokenRepository;

    /** @var DeviceTokensRepository */
    private $deviceTokensRepository;

    /** @var UserManager */
    private $userManager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logoutHandler = $this->inject(UsersLogoutHandler::class);
        $this->getDeviceTokenApiHandler = $this->inject(GetDeviceTokenApiHandler::class);
        $this->accessTokenRepository = $this->getRepository(AccessTokensRepository::class);
        $this->deviceTokensRepository = $this->getRepository(DeviceTokensRepository::class);
        $this->userManager = $this->inject(UserManager::class);
    }

    public function testUserLogout()
    {
        $user1 = $this->addUser('user@user.sk', 'password');
        $accessToken1 = $this->accessTokenRepository->add($user1, 3);
        $accessToken2 = $this->accessTokenRepository->add($user1, 3);
        $this->assertEquals(2, $this->accessTokenRepository->all()->count());

        $response = $this->logoutHandler->handle(new TestUserTokenAuthorization($accessToken1, $user1));
        $this->assertEquals(JsonResponse::class, get_class($response));
        $this->assertEquals(Response::S200_OK, $response->getHttpCode());

        // Check that after successful logout, only one access_token is kept
        $this->assertEquals(1, $this->accessTokenRepository->all()->count());
        $storedToken = $this->accessTokenRepository->all(1)->fetch()->token;
        $this->assertEquals($accessToken2, $storedToken);
    }

    public function testDeviceTokenLogout()
    {
        $user1 = $this->addUser('user1@user.sk', 'password');
        $userUnclaimed = $this->inject(UnclaimedUser::class)->createUnclaimedUser();
        $user2 = $this->addUser('user2@user.sk', 'password');

        $deviceToken = $this->deviceTokensRepository->generate('test');

        // Pair 2 users with device_token (1 has to be unclaimed since device token can be paired only to single standard user)
        $accessToken1 = $this->accessTokenRepository->add($user1, 3);
        $this->accessTokenRepository->pairWithDeviceToken($accessToken1, $deviceToken);

        $accessTokenUnclaimed = $this->accessTokenRepository->add($userUnclaimed, 3);
        $this->accessTokenRepository->pairWithDeviceToken($accessTokenUnclaimed, $deviceToken);

        $accessToken2 = $this->accessTokenRepository->add($user2, 3);

        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $deviceToken->token;
        $response = $this->logoutHandler->handle($this->getUserTokenAuthorization());
        $this->assertEquals(JsonResponse::class, get_class($response));
        $this->assertEquals(Response::S200_OK, $response->getHttpCode());

        // Check that only single token is kept
        $this->assertEquals(1, $this->accessTokenRepository->all()->count());
        $this->assertEquals($accessToken2->token, $this->accessTokenRepository->all(1)->fetch()->token);
    }

    private function getUserTokenAuthorization()
    {
        /** @var UserTokenAuthorization $userTokenAuthorization */
        $userTokenAuthorization = $this->inject(UserTokenAuthorization::class);
        $userTokenAuthorization->authorized();
        return $userTokenAuthorization;
    }

    private function addUser($email, $password)
    {
        return $this->userManager->addNewUser($email, false, 'test', null, false, $password, false);
    }
}
