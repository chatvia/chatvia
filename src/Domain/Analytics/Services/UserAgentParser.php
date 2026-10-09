<?php

namespace Domain\Analytics\Services;

use Domain\Analytics\Data\UserAgentData;

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

    /**
     * @deprecated Use {@see self::detect()} on an injected instance; kept for
     *             callers still on the array shape.
     *
     * @return array{device_type: ?string, browser: ?string, os: ?string}
     */
    public static function parse(?string $userAgent): array
    {
        $data = new self()->detect($userAgent);

        return ['device_type' => $data->deviceType, 'browser' => $data->browser, 'os' => $data->os];
    }

    private function deviceType(string $userAgent): string
    {
        return match (true) {
            stripos($userAgent, 'ipad') !== false => 'iPad',
            stripos($userAgent, 'ipod') !== false => 'iPod',
            stripos($userAgent, 'iphone') !== false => 'iPhone',
            preg_match('/windows phone/i', $userAgent) === 1 => 'Windows Phone',
            stripos($userAgent, 'android') !== false && stripos($userAgent, 'mobile') === false => 'Android Tablet',
            stripos($userAgent, 'android') !== false => 'Android Phone',
            stripos($userAgent, 'playbook') !== false => 'BlackBerry Tablet',
            stripos($userAgent, 'blackberry') !== false => 'BlackBerry',
            preg_match('/kindle|silk/i', $userAgent) === 1 => 'Kindle',
            preg_match('/(webos|iemobile|opera mini)/i', $userAgent) === 1 => 'Mobile',
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
        if (preg_match('/(iphone|ipad|ipod)/i', $userAgent)) {
            return 'iOS';
        }

        // Android: major version only.
        if (preg_match('/android\s+([\d.]+)/i', $userAgent, $matches)) {
            return 'Android '.explode('.', $matches[1])[0];
        }

        if (preg_match('/mac os x\s+([\d_.]+)/i', $userAgent, $matches)) {
            return 'macOS '.str_replace('_', '.', $matches[1]);
        }

        if (preg_match('/windows nt\s+([\d.]+)/i', $userAgent, $matches)) {
            return 'Windows '.$matches[1];
        }

        return $this->firstMatch(self::FALLBACK_OS_PATTERNS, $userAgent) ?? 'Unknown';
    }

    /**
     * @param  array<string, string>  $patterns  label => regex, checked in order
     */
    private function firstMatch(array $patterns, string $userAgent): ?string
    {
        $haystack = strtolower($userAgent);

        foreach ($patterns as $label => $pattern) {
            if (preg_match($pattern, $haystack)) {
                return $label;
            }
        }

        return null;
    }
}
