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
 * Private, reserved and malformed addresses are never looked up.
 */
class IP2Country
{
    public function locate(string $ip): ?IpLocationData
    {
        if (! $this->isPublicIp($ip)) {
            return null;
        }

        $key = (string) config('services.ip2location.key');

        // No key set up (Settings → Platform): visitor location is off.
        if ($key === '') {
            return null;
        }

        $url = rtrim((string) config('services.ip2location.endpoint'), '/').'/';

        try {
            $result = Http::withToken($key)
                ->get($url, ['ip' => $ip])
                ->throw()
                ->json();

            return IpLocationData::fromApiResponse((array) $result);
        } catch (Throwable $e) {
            report($this->withoutKey($e, $key));

            return null;
        }
    }

    /**
     * The ISO-3166 alpha-2 country code (lower case) for an IP, or null when
     * it can't be resolved.
     */
    public function countryCode(string $ip): ?string
    {
        $code = $this->locate($ip)?->countryCode;

        return $code !== null && $code !== '' ? $code : null;
    }

    private function isPublicIp(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    }

    /**
     * A copy of the failure that is safe to report: the message with the API
     * key masked, and no link to the original exception (its request and
     * previous exceptions could still carry the key).
     */
    private function withoutKey(Throwable $e, string $key): RuntimeException
    {
        $message = $key === '' ? $e->getMessage() : str_replace($key, '[redacted]', $e->getMessage());

        return new RuntimeException('IP2Location lookup failed ('.$e::class.'): '.$message);
    }
}
