<?php

namespace Crm\UsersModule\DataProvider;

use Crm\ApplicationModule\DataProvider\DataProviderInterface;
use Nette\Database\Table\IRow;

interface AccessTokenDataProviderInterface extends DataProviderInterface
{
    /**
     * canUnpairDeviceToken should determine, whether access token can be unpaired from device token.
     *
     * @param IRow $accessToken
     * @param IRow $deviceToken
     * @return bool
     */
    public function canUnpairDeviceToken(IRow $accessToken, IRow $deviceToken): bool;
}
