<?php

namespace Crm\UsersModule\Auth;

use Crm\ApiModule\Authorization\ApiAuthorizationInterface;
use Crm\UsersModule\Repositories\DeviceTokensRepository;
use Crm\UsersModule\Repository\AccessTokensRepository;
use League\Event\Emitter;
use Nette\Security\IAuthorizator;

class UserTokenAuthorization implements UsersApiAuthorizationInterface, AccessTokensApiAuthorizationInterface
{
    protected $accessTokensRepository;

    protected $deviceTokensRepository;

    /** @var UsersApiAuthorizationInterface */
    protected $authorizator = null;

    /** @var UsersApiAuthorizationInterface[] */
    protected $authorizators = [];

    protected $emitter;

    public function __construct(
        AccessTokensRepository $accessTokensRepository,
        DeviceTokensRepository $deviceTokensRepository,
        Emitter $emitter
    ) {
        $this->accessTokensRepository = $accessTokensRepository;
        $this->deviceTokensRepository = $deviceTokensRepository;
        $this->emitter = $emitter;
    }

    public function registerAuthorizator(string $source, ApiAuthorizationInterface $authorizator, bool $useAlways = false)
    {
        $this->authorizators[$source] = [
            'source' => $source,
            'authorizator' => $authorizator,
            'useAlways' => $useAlways,
        ];
    }

    public function authorized($resource = IAuthorizator::ALL)
    {
        foreach ($this->authorizators as $authDef) {
            if ($authDef['useAlways'] || (isset($_GET['source']) && $authDef['source'] === $_GET['source'])) {
                $this->authorizator = $authDef['authorizator'];
                if ($this->authorizator->authorized($resource)) {
                    return true;
                }
                continue;
            }
        }

        $this->authorizator = new DefaultUserTokenAuthorization($this->accessTokensRepository, $this->emitter);
        if ($this->authorizator->authorized($resource)) {
            return true;
        }

        $this->authorizator = new DeviceTokenAuthorization(
            $this->accessTokensRepository,
            $this->deviceTokensRepository,
            $this->emitter
        );
        return $this->authorizator->authorized($resource);
    }

    public function getErrorMessage()
    {
        if (is_null($this->authorizator)) {
            throw new \Exception('Authorize token first - use `authorized` method.');
        }
        return $this->authorizator->getErrorMessage();
    }

    public function getAuthorizedData()
    {
        if (is_null($this->authorizator)) {
            throw new \Exception('Authorize token first - use `authorized` method.');
        }
        return $this->authorizator->getAuthorizedData();
    }

    public function getAuthorizedUsers()
    {
        if (is_null($this->authorizator)) {
            throw new \Exception('Authorize token first - use `authorized` method.');
        }
        return $this->authorizator->getAuthorizedUsers();
    }

    public function getAccessTokens()
    {
        if (is_null($this->authorizator)) {
            throw new \Exception('Authorize token first - use `authorized` method.');
        }
        return $this->authorizator->getAccessTokens();
    }
}
