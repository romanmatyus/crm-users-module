<?php

namespace Crm\UsersModule\Auth\Access;

use Crm\ContentModule\Access\ContentShareInterface;
use Crm\ContentModule\Access\UrlShareInterface;

class DummyStorage implements StorageInterface, ContentShareInterface, UrlShareInterface
{
    public function addToken($token, $type = 'access')
    {
        return true;
    }

    public function removeToken($token, $type = 'access')
    {
        return true;
    }

    public function tokenExists($token, $type = 'access')
    {
        return true;
    }

    public function allTokens($type = 'access')
    {
        return true;
    }

    public function getUrls($key)
    {
        return true;
    }

    public function addUrl($key, $url)
    {
        return true;
    }

    public function removeUrl($key, $url)
    {
        return true;
    }

    public function urlExists($key, $url)
    {
        return true;
    }


    public function addContentShareToken($url, $token)
    {
        return true;
    }


    public function existsContentShareToken($url, $token)
    {
        return true;
    }


    public function removeContentShareToken($url, $token)
    {
        return true;
    }


    public function getContentUse()
    {
        return true;
    }
}
