<?php

use Domain\Analytics\Services\UserAgentParser;

/**
 * The device type, browser and operating system read from common user-agent
 * strings, for the session details and the device breakdowns.
 */
it('reads the device, browser and OS', function (string $userAgent, string $device, string $browser, string $os): void {
    $result = new UserAgentParser()->detect($userAgent);

    expect($result->deviceType)->toBe($device)
        ->and($result->browser)->toBe($browser)
        ->and($result->os)->toBe($os);
})->with([
    'chrome on windows' => ['Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36', 'Desktop', 'Chrome', 'Windows 10.0'],
    'safari on iphone' => ['Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1', 'iPhone', 'Safari', 'iOS'],
    'firefox on mac' => ['Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:120.0) Gecko/20100101 Firefox/120.0', 'Desktop', 'Firefox', 'macOS 10.15'],
    'chrome on android' => ['Mozilla/5.0 (Linux; Android 13; SM-S908B) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/112.0.0.0 Mobile Safari/537.36', 'Android Phone', 'Chrome', 'Android 13'],
    'edge on windows' => ['Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36 Edg/120.0.0.0', 'Desktop', 'Edge', 'Windows 10.0'],
    'safari on ipad' => ['Mozilla/5.0 (iPad; CPU OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1', 'iPad', 'Safari', 'iOS'],
    'opera on linux' => ['Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36 OPR/106.0.0.0', 'Desktop', 'Opera', 'Linux'],
    'android tablet' => ['Mozilla/5.0 (Linux; Android 11; SM-T870) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/96.0.4664.45 Safari/537.36', 'Android Tablet', 'Chrome', 'Android 11'],
    'ipod' => ['Mozilla/5.0 (iPod touch; CPU iPhone OS 14_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/14.0 Mobile/15E148 Safari/604.1', 'iPod', 'Safari', 'iOS'],
]);
