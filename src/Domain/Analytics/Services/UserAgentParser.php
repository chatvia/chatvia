<?php

namespace Domain\Analytics\Services;

use Domain\Analytics\Data\UserAgentData;
use Illuminate\Support\Str;

/**
 * Reads the device type, browser and operating system from a user-agent
 * string with simple pattern checks — enough for the session details and the
 * device breakdowns, without a user-agent database. Unknown values come back
 * as "Unknown" (browser, OS) or "Desktop" (device type).
 */
class UserAgentParser
{
    private const array BROWSER_PATTERNS = [
        'Edge' => '/edg[ea]?\/([\d.]+)/i',
        'Opera' => '/(opera|opr)[\/\s]+([\d.]+)/i',
        'Silk' => '/silk\/([\d.]+)/i',
        'Chrome' => '/chrome\/([\d.]+)/i',
        'Safari' => '/version\/([\d.]+).*safari/i',
        'Firefox' => '/firefox\/([\d.]+)/i',
        'Internet Explorer' => '/(msie|trident).*([\d.]+)/i',
    ];

    private const array FALLBACK_OS_PATTERNS = [
        'Windows' => '/windows/i',
        'Mac OS' => '/macintosh|mac os/i',
        'Linux' => '/linux/i',
        'Ubuntu' => '/ubuntu/i',
        'Chrome OS' => '/cros/i',
    ];

    public function detect(?string $userAgent): UserAgentData
    {
        if (! $userAgent) {
            return new UserAgentData;
        }

        return new UserAgentData(
            deviceType: $this->deviceType($userAgent),
            browser: $this->browser($userAgent),
            os: $this->operatingSystem($userAgent),
        );
    }

    private function deviceType(string $userAgent): string
    {
        return match (true) {
            Str::contains($userAgent, 'ipad', ignoreCase: true) => 'iPad',
            Str::contains($userAgent, 'ipod', ignoreCase: true) => 'iPod',
            Str::contains($userAgent, 'iphone', ignoreCase: true) => 'iPhone',
            Str::isMatch('/windows phone/i', $userAgent) => 'Windows Phone',
            Str::contains($userAgent, 'android', ignoreCase: true) && ! Str::contains($userAgent, 'mobile', ignoreCase: true) => 'Android Tablet',
            Str::contains($userAgent, 'android', ignoreCase: true) => 'Android Phone',
            Str::contains($userAgent, 'playbook', ignoreCase: true) => 'BlackBerry Tablet',
            Str::contains($userAgent, 'blackberry', ignoreCase: true) => 'BlackBerry',
            Str::isMatch('/kindle|silk/i', $userAgent) => 'Kindle',
            Str::isMatch('/(webos|iemobile|opera mini)/i', $userAgent) => 'Mobile',
            default => 'Desktop',
        };
    }

    private function browser(string $userAgent): string
    {
        return $this->firstMatch(self::BROWSER_PATTERNS, $userAgent) ?? 'Unknown';
    }

    private function operatingSystem(string $userAgent): string
    {
        // iOS devices (iPhone, iPad, iPod)
        if (Str::isMatch('/(iphone|ipad|ipod)/i', $userAgent)) {
            return 'iOS';
        }

        // Android: major version only.
        $android = Str::match('/android\s+([\d.]+)/i', $userAgent);
        if ($android !== '') {
            return 'Android '.Str::before($android, '.');
        }

        $mac = Str::match('/mac os x\s+([\d_.]+)/i', $userAgent);
        if ($mac !== '') {
            return 'macOS '.Str::replace('_', '.', $mac);
        }

        $windows = Str::match('/windows nt\s+([\d.]+)/i', $userAgent);
        if ($windows !== '') {
            return 'Windows '.$windows;
        }

        return $this->firstMatch(self::FALLBACK_OS_PATTERNS, $userAgent) ?? 'Unknown';
    }

    /**
     * @param  array<string, string>  $patterns  label => regex, checked in order
     */
    private function firstMatch(array $patterns, string $userAgent): ?string
    {
        $haystack = Str::lower($userAgent);

        foreach ($patterns as $label => $pattern) {
            if (Str::isMatch($pattern, $haystack)) {
                return $label;
            }
        }

        return null;
    }
}
