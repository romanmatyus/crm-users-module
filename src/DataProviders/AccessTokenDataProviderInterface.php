<?php

namespace Crm\UsersModule\DataProvider;

use Crm\ApplicationModule\DataProvider\DataProviderInterface;
use Nette\Database\Table\ActiveRow;

interface AccessTokenDataProviderInterface extends DataProviderInterface
{
    /**
     * canUnpairDeviceToken should determine, whether access token can be unpaired from device token.
     *
     * @param ActiveRow $accessToken
     * @param ActiveRow $deviceToken
     * @return bool
     */
    public function canUnpairDeviceToken(ActiveRow $accessToken, ActiveRow $deviceToken): bool;
}
