<?php

namespace Crm\UsersModule\Auth;

use Nette\Http\Request;
use Nette\Http\Url;
use Nette\InvalidArgumentException;
use Nette\Utils\Strings;
use Tracy\Debugger;

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

        try {
            $urlHost = $this->getHost($url);
        } catch (InvalidArgumentException $iae) {
            // no need to log it; this can be issue on client side
            return false;
        }


        foreach ($allowedDomains as $domain) {
            try {
                if (Strings::endsWith($urlHost, $this->getHost($domain))) {
                    return true;
                }
            } catch (InvalidArgumentException $iae) {
                Debugger::log('Invalid allowed domain [' . $domain . ' ]', Debugger::ERROR);
                return false;
            }
        }
        return false;
    }

    /**
     * @throws InvalidArgumentException
     */
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
