<?php

declare(strict_types=1);

namespace App\Admin\Settings;

use App\Admin\Settings\Data\ConnectionCheckData;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Checks a Firecrawl key with a call that costs no credits: the team's
 * credit usage. Only a refused key or a server that cannot be reached
 * counts as not connected, as a server of your own may not have the call.
 */
final class FirecrawlCheck
{
    public function check(string $apiKey, ?string $endpoint = null): ConnectionCheckData
    {
        $endpoint = rtrim(filled($endpoint) ? $endpoint : (string) config('services.firecrawl.endpoint'), '/');

        try {
            $status = Http::withToken($apiKey)->acceptJson()->timeout(10)->get("{$endpoint}/team/credit-usage")->status();
        } catch (ConnectionException) {
            return new ConnectionCheckData(false, message: "Firecrawl could not be reached at {$endpoint}.");
        }

        return in_array($status, [401, 403], true)
            ? new ConnectionCheckData(false, keyRefused: true, message: 'Firecrawl refused this key. Check that you copied all of it.')
            : new ConnectionCheckData(true);
    }
}
