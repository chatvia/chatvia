<?php

namespace Tests\Unit;

use Domain\Analytics\Services\UserAgentParser;
use PHPUnit\Framework\TestCase;

class UserAgentParserTest extends TestCase
{
    public function test_parses_chrome_on_windows(): void
    {
        $userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';
        $result = UserAgentParser::parse($userAgent);

        $this->assertEquals('Desktop', $result['device_type']);
        $this->assertEquals('Chrome', $result['browser']);
        $this->assertEquals('Windows 10.0', $result['os']);
    }

    public function test_parses_safari_on_iphone(): void
    {
        $userAgent = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1';
        $result = UserAgentParser::parse($userAgent);

        $this->assertEquals('iPhone', $result['device_type']);
        $this->assertEquals('Safari', $result['browser']);
        $this->assertEquals('iOS', $result['os']);
    }

    public function test_parses_firefox_on_mac(): void
    {
        $userAgent = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:120.0) Gecko/20100101 Firefox/120.0';
        $result = UserAgentParser::parse($userAgent);

        $this->assertEquals('Desktop', $result['device_type']);
        $this->assertEquals('Firefox', $result['browser']);
        $this->assertEquals('macOS 10.15', $result['os']);
    }

    public function test_parses_chrome_on_android(): void
    {
        $userAgent = 'Mozilla/5.0 (Linux; Android 13; SM-S908B) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/112.0.0.0 Mobile Safari/537.36';
        $result = UserAgentParser::parse($userAgent);

        $this->assertEquals('Android Phone', $result['device_type']);
        $this->assertEquals('Chrome', $result['browser']);
        $this->assertEquals('Android 13', $result['os']);
    }

    public function test_parses_edge_on_windows(): void
    {
        $userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36 Edg/120.0.0.0';
        $result = UserAgentParser::parse($userAgent);

        $this->assertEquals('Desktop', $result['device_type']);
        $this->assertEquals('Edge', $result['browser']);
        $this->assertEquals('Windows 10.0', $result['os']);
    }

    public function test_parses_safari_on_ipad(): void
    {
        $userAgent = 'Mozilla/5.0 (iPad; CPU OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1';
        $result = UserAgentParser::parse($userAgent);

        $this->assertEquals('iPad', $result['device_type']);
        $this->assertEquals('Safari', $result['browser']);
        $this->assertEquals('iOS', $result['os']);
    }

    public function test_returns_null_for_empty_user_agent(): void
    {
        $result = UserAgentParser::parse(null);

        $this->assertNull($result['device_type']);
        $this->assertNull($result['browser']);
        $this->assertNull($result['os']);
    }

    public function test_parses_opera_on_linux(): void
    {
        $userAgent = 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36 OPR/106.0.0.0';
        $result = UserAgentParser::parse($userAgent);

        $this->assertEquals('Desktop', $result['device_type']);
        $this->assertEquals('Opera', $result['browser']);
        $this->assertEquals('Linux', $result['os']);
    }

    public function test_parses_android_tablet(): void
    {
        $userAgent = 'Mozilla/5.0 (Linux; Android 11; SM-T870) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/96.0.4664.45 Safari/537.36';
        $result = UserAgentParser::parse($userAgent);

        $this->assertEquals('Android Tablet', $result['device_type']);
        $this->assertEquals('Chrome', $result['browser']);
        $this->assertEquals('Android 11', $result['os']);
    }

    public function test_parses_ipod(): void
    {
        $userAgent = 'Mozilla/5.0 (iPod touch; CPU iPhone OS 14_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/14.0 Mobile/15E148 Safari/604.1';
        $result = UserAgentParser::parse($userAgent);

        $this->assertEquals('iPod', $result['device_type']);
        $this->assertEquals('Safari', $result['browser']);
        $this->assertEquals('iOS', $result['os']);
    }

    public function test_parses_windows_phone(): void
    {
        $userAgent = 'Mozilla/5.0 (Windows Phone 10.0; Android 6.0.1; Microsoft; Lumia 950) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/52.0.2743.116 Mobile Safari/537.36 Edge/15.14977';
        $result = UserAgentParser::parse($userAgent);

        $this->assertEquals('Windows Phone', $result['device_type']);
        $this->assertEquals('Edge', $result['browser']);
    }

    public function test_parses_blackberry(): void
    {
        $userAgent = 'Mozilla/5.0 (BlackBerry; U; BlackBerry 9900; en) AppleWebKit/534.11+ (KHTML, like Gecko) Version/7.1.0.346 Mobile Safari/534.11+';
        $result = UserAgentParser::parse($userAgent);

        $this->assertEquals('BlackBerry', $result['device_type']);
        $this->assertEquals('Safari', $result['browser']);
    }

    public function test_parses_kindle(): void
    {
        $userAgent = 'Mozilla/5.0 (Linux; U; en-us; KFAPWI Build/JDQ39) AppleWebKit/535.19 (KHTML, like Gecko) Silk/3.13 Safari/535.19 Silk-Accelerated=true';
        $result = UserAgentParser::parse($userAgent);

        $this->assertEquals('Kindle', $result['device_type']);
        $this->assertEquals('Silk', $result['browser']);
    }
}
