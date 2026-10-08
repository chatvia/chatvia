<?php

namespace Domain\Analytics\Services;

use Domain\Analytics\Data\IpLocationData;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * IP geolocation through the IP2Location.io API. The API key is sent as a
 * bearer token, never in the URL, so it cannot end up in logs or error reports
 * that include the request URL. A failed lookup is reported (with the key
 * masked) and returns null, so callers treat it as "unknown location".
 */
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

        $key = (string) config('services.ip2location.key');
        $url = rtrim((string) config('services.ip2location.endpoint'), '/').'/';

        try {
            $result = Http::withToken($key)
                ->get($url, ['ip' => $ip])
                ->throw()
                ->json();

            return IpLocationData::fromApiResponse($result);
        } catch (Throwable $e) {
            report(self::withoutKey($e, $key));

            return null;
        }
    }

    /**
     * A copy of the failure that is safe to report: the message with the API
     * key masked, and no link to the original exception (its request and
     * previous exceptions could still carry the key).
     */
    private static function withoutKey(Throwable $e, string $key): RuntimeException
    {
        $message = $key === '' ? $e->getMessage() : str_replace($key, '[redacted]', $e->getMessage());

        return new RuntimeException('IP2Location lookup failed ('.$e::class.'): '.$message);
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
