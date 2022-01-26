<?php

namespace Crm\UsersModule\Auth;

use Crm\ApiModule\Authorization\ApiAuthorizationInterface;
use League\Event\Emitter;
use Nette\Security\IAuthorizator;

class UserTokenAuthorization implements UsersApiAuthorizationInterface, AccessTokensApiAuthorizationInterface
{
    /** @var UsersApiAuthorizationInterface */
    protected $authorizator = null;

    /** @var UsersApiAuthorizationInterface[] */
    protected $authorizators = [];

    protected DefaultUserTokenAuthorization $defaultUserTokenAuthorization;

    protected DeviceTokenAuthorization $deviceTokenAuthorization;

    protected Emitter $emitter;

    public function __construct(
        DefaultUserTokenAuthorization $defaultUserTokenAuthorization,
        DeviceTokenAuthorization $deviceTokenAuthorization,
        Emitter $emitter
    ) {
        $this->defaultUserTokenAuthorization = $defaultUserTokenAuthorization;
        $this->deviceTokenAuthorization = $deviceTokenAuthorization;
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
            }
        }

        $this->authorizator = clone $this->defaultUserTokenAuthorization;
        if ($this->authorizator->authorized($resource)) {
            return true;
        }

        $this->authorizator = clone $this->deviceTokenAuthorization;
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
