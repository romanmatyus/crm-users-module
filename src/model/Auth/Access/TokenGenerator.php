<?php

namespace Crm\UsersModule\Auth\Access;

class TokenGenerator
{
    public function generate($param1 = '', $param2 = '')
    {
        return md5(time() . rand(1000, 10000) . $param1 . $param2 . rand(10000, 1000) . time());
    }
}
