<?php

namespace App\Services;

class UserAgentParser
{
    public static function parse(?string $userAgent): array
    {
        if (! $userAgent) {
            return [
                'device_type' => null,
                'browser' => null,
                'os' => null,
            ];
        }

        return [
            'device_type' => self::getDeviceType($userAgent),
            'browser' => self::getBrowser($userAgent),
            'os' => self::getOS($userAgent),
        ];
    }

    private static function getDeviceType(string $userAgent): ?string
    {
        if (stripos($userAgent, 'ipad') !== false) {
            return 'iPad';
        }

        if (stripos($userAgent, 'ipod') !== false) {
            return 'iPod';
        }

        if (stripos($userAgent, 'iphone') !== false) {
            return 'iPhone';
        }

        if (preg_match('/windows phone/i', $userAgent)) {
            return 'Windows Phone';
        }

        if (stripos($userAgent, 'android') !== false && stripos($userAgent, 'mobile') === false) {
            return 'Android Tablet';
        }

        if (stripos($userAgent, 'android') !== false && stripos($userAgent, 'mobile') !== false) {
            return 'Android Phone';
        }

        if (stripos($userAgent, 'playbook') !== false) {
            return 'BlackBerry Tablet';
        }

        if (stripos($userAgent, 'blackberry') !== false) {
            return 'BlackBerry';
        }

        if (preg_match('/kindle|silk/i', $userAgent)) {
            return 'Kindle';
        }

        if (preg_match('/(webos|iemobile|opera mini)/i', $userAgent)) {
            return 'Mobile';
        }

        return 'Desktop';
    }

    private static function getBrowser(string $userAgent): ?string
    {
        $browsers = [
            'Edge' => '/edg[ea]?\/([\d.]+)/i',
            'Opera' => '/(opera|opr)[\/\s]+([\d.]+)/i',
            'Silk' => '/silk\/([\d.]+)/i',
            'Chrome' => '/chrome\/([\d.]+)/i',
            'Safari' => '/version\/([\d.]+).*safari/i',
            'Firefox' => '/firefox\/([\d.]+)/i',
            'Internet Explorer' => '/(msie|trident).*([\d.]+)/i',
        ];

        foreach ($browsers as $browser => $pattern) {
            if (preg_match($pattern, strtolower($userAgent))) {
                return $browser;
            }
        }

        return 'Unknown';
    }

    private static function getOS(string $userAgent): ?string
    {
        // iOS devices (iPhone, iPad, iPod)
        if (preg_match('/(iphone|ipad|ipod)/i', $userAgent)) {
            return 'iOS';
        }

        // Android with version
        if (preg_match('/android\s+([\d.]+)/i', $userAgent, $matches)) {
            $version = $matches[1];
            // Return just major version for Android
            $majorVersion = explode('.', $version)[0];

            return 'Android '.$majorVersion;
        }

        // Mac OS X with version
        if (preg_match('/mac os x\s+([\d_.]+)/i', $userAgent, $matches)) {
            $version = str_replace('_', '.', $matches[1]);

            return 'macOS '.$version;
        }

        // Windows with NT version
        if (preg_match('/windows nt\s+([\d.]+)/i', $userAgent, $matches)) {
            return 'Windows '.$matches[1];
        }

        // Fallback OS detection
        $osPlatforms = [
            'Windows' => '/windows/i',
            'Mac OS' => '/macintosh|mac os/i',
            'Linux' => '/linux/i',
            'Ubuntu' => '/ubuntu/i',
            'Chrome OS' => '/cros/i',
        ];

        foreach ($osPlatforms as $platform => $pattern) {
            if (preg_match($pattern, strtolower($userAgent))) {
                return $platform;
            }
        }

        return 'Unknown';
    }
}
