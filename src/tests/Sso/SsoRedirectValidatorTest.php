<?php

namespace Crm\UsersModule\Tests\Sso;

use Crm\UsersModule\Auth\Sso\SsoRedirectValidator;
use Nette\Http\Request;
use Nette\Http\UrlScript;
use PHPUnit\Framework\TestCase;

class SsoRedirectValidatorTest extends TestCase
{
    /**
     * @dataProvider provider
     */
    public function testValidation(string $crmHost, array $allowedDomains, string $urlToValidate, bool $isValidRedirect): void
    {
        $r = new Request(new UrlScript($crmHost));
        $validator = new SsoRedirectValidator($r);
        $validator->addAllowedDomains(...$allowedDomains);
        $this->assertEquals($isValidRedirect, $validator->isAllowed($urlToValidate));
    }

    public function provider(): array
    {
        return [
            ['https://predplatne.dennikn.sk', [], 'https://predplatne.dennikn.sk', true],
            ['https://predplatne.dennikn.sk', [], 'https://predplatne.dennikn.sk/some-path', true],
            ['https://predplatne.dennikn.sk', [], 'https://dennikn.sk', true],
            ['https://predplatne.dennikn.sk', [], 'https://dennikn.sk', true],
            ['https://predplatne.dennikn.sk', [], 'http://other.dennikn.sk', true],
            ['http://example.com', [], 'sub.example.com', true],
            ['https://predplatne.dennikn.sk', ['example.com'], 'sub1.example.com', true],
            ['https://predplatne.dennikn.sk', ['example.com/some-path'], 'sub1.example.com', true],
            ['https://predplatne.dennikn.sk', ['https://example.com/some-path'], 'sub1.example.com', true],
            // invalid - different domain, different subdomain in allowed URLs
            ['https://predplatne.dennikn.sk', [], 'example.com', false],
            ['https://predplatne.dennikn.sk', ['sub1.example.com'], 'sub2.example.com', false],
        ];
    }
}
