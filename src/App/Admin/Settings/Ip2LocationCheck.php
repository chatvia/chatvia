<?php

declare(strict_types=1);

namespace App\Admin\Settings;

use App\Admin\Settings\Data\ConnectionCheckData;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Checks an IP2Location.io key by looking up one public address, the way
 * visitors are located (the key as a bearer token, never in the URL). The
 * API has no free call, so each check uses one query of the plan.
 */
final class Ip2LocationCheck
{
    /** A public address that always has a country. */
    private const string PROBE_IP = '8.8.8.8';

    public function check(string $apiKey, ?string $endpoint = null): ConnectionCheckData
    {
        $endpoint = Str::rtrim(filled($endpoint) ? $endpoint : (string) config('services.ip2location.endpoint'), '/');

        try {
            $response = Http::withToken($apiKey)->acceptJson()->timeout(10)->get("{$endpoint}/", ['ip' => self::PROBE_IP]);
        } catch (ConnectionException) {
            return new ConnectionCheckData(false, message: "IP2Location could not be reached at {$endpoint}.");
        }

        if (collect([401, 403])->containsStrict($response->status())) {
            return new ConnectionCheckData(false, keyRefused: true, message: 'IP2Location refused this key, or it has no queries left this month.');
        }

        if (! $response->successful() || blank($response->json('country_code'))) {
            return new ConnectionCheckData(false, message: "IP2Location did not return a country (status {$response->status()}).");
        }

        return new ConnectionCheckData(true);
    }
}
