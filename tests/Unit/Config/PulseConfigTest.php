<?php

declare(strict_types=1);

use Laravel\Pulse\Recorders\SlowOutgoingRequests;

/**
 * Pulse keeps the slowest outgoing requests by URL. A query string can carry
 * a key or a signed token, so config/pulse.php groups each URL to the part
 * before it.
 */
function pcGroup(string $url): string
{
    foreach (config('pulse.recorders.'.SlowOutgoingRequests::class.'.groups') as $pattern => $replacement) {
        $grouped = preg_replace($pattern, $replacement, $url, count: $count);

        if ($count > 0 && $grouped !== null) {
            return $grouped;
        }
    }

    return $url;
}

it('drops the query string and fragment of a slow outgoing request', function (string $url, string $kept): void {
    expect(pcGroup($url))->toBe($kept);
})->with([
    'query' => ['https://api.example.com/v1/search?key=secret&q=x', 'https://api.example.com/v1/search'],
    'fragment' => ['https://api.example.com/v1/page#token=abc', 'https://api.example.com/v1/page'],
    'none' => ['https://api.example.com/v1/models', 'https://api.example.com/v1/models'],
]);
