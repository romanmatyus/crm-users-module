<?php

namespace Crm\UsersModule\Auth;

use Nette\Http\Request;
use Nette\Http\Url;
use Nette\Utils\Strings;

class SignInRedirectValidator
{
    private $request;

    private $allowedDomains = [];

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function addAllowedDomains(string...$domains): void
    {
        $this->allowedDomains = array_merge($this->allowedDomains, $domains);
    }

    public function isAllowed(string $url): bool
    {
        // $url is validated against current CRM domain - $url has to share at least the second level domain with CRM host.
        // e.g. if your CRM is available at `crm.example.com`, any domain passing `*.example.com` will be considered as a valid redirect.
        $currentHost = $this->request->getUrl()->getHost();
        $firstAndSecondLevelDomain = implode('.', array_slice(explode('.', $currentHost), -2, 2));

        $allowedDomains = $this->allowedDomains;
        $allowedDomains[] = $firstAndSecondLevelDomain;

        foreach ($allowedDomains as $domain) {
            if (Strings::endsWith($this->getHost($url), $this->getHost($domain))) {
                return true;
            }
        }
        return false;
    }

    private function getHost(string $urlString): string
    {
        if (!Strings::startsWith($urlString, 'https://') && !Strings::startsWith($urlString, 'http://')) {
            // PHP's url_parse() returns whole string as 'path' if scheme is omitted
            // therefore if missing, add arbitrary scheme to correctly parse $urlString
            // since user may forget to specify scheme
            $urlString = 'https://' . $urlString;
        }

        return (new Url($urlString))->getHost();
    }
}
