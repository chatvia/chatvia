<?php

namespace Domain\Analytics\Services;

use Domain\Analytics\Data\IpLocationData;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class IP2Country
{
    /**
     * Get location data by IP address.
     */
    public static function getLocationByIP(string $ip): ?IpLocationData
    {
        // Validate IP address format
        if (! filter_var($ip, FILTER_VALIDATE_IP)) {
            return null;
        }

        // Skip geolocation for private/local IP addresses
        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return null;
        }

        $url = Str::of(rtrim(config('services.ip2location.endpoint'), '/').'/')
            ->append('?key='.config('services.ip2location.key'))
            ->append('&ip='.$ip)
            ->toString();

        try {
            $result = Http::get($url)
                ->throw()
                ->json();

            return IpLocationData::fromApiResponse($result);
        } catch (\Exception $e) {
            report($e);

            return null;
        }
    }

    /**
     * Get country code by IP address.
     *
     * @deprecated Use getLocationByIP() instead for full location data.
     */
    public static function getCountryByIP(string $ip): string
    {
        $location = self::getLocationByIP($ip);

        return $location->countryCode ?? '';
    }
}
