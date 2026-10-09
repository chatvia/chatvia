<?php

declare(strict_types=1);

use App\Mcp\McpCallerContext;
use App\Mcp\Servers\CurrencyConverterServer;
use App\Mcp\Tools\Currency\ExchangeRateTool;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Laravel\Mcp\Server\Testing\TestResponse;

/**
 * Each workspace brings its own ExchangeRate-API key, so cached rates must be
 * per key: a bad or missing key never rides on another workspace's cache.
 */
function convertWithKey(string $key): TestResponse
{
    app()->instance(McpCallerContext::class, new McpCallerContext(connectorApiKey: $key));

    return CurrencyConverterServer::tool(ExchangeRateTool::class, ['to' => 'EUR', 'from' => 'USD', 'amount' => 10]);
}

it('caches rates per API key and calls the configured base URL', function (): void {
    config(['services.exchange_rate.base_url' => 'https://rates.example.test/v6']);

    Http::fake([
        'rates.example.test/v6/good-key/latest/USD' => Http::response([
            'result' => 'success',
            'conversion_rates' => ['USD' => 1, 'EUR' => 0.5],
        ]),
        'rates.example.test/v6/bad-key/latest/USD' => Http::response(['result' => 'error', 'error-type' => 'invalid-key'], 403),
    ]);

    convertWithKey('good-key')->assertOk()->assertSee('5.0000 EUR');
    convertWithKey('good-key')->assertOk();

    // A different (invalid) key must not be answered from good-key's cache.
    convertWithKey('bad-key')->assertHasErrors();

    Http::assertSentCount(2);
});

it('does not surface connection errors that contain the key-bearing URL', function (): void {
    Http::fake(fn () => throw new ConnectionException('cURL error 28 for https://v6.exchangerate-api.com/v6/secret-key/latest/USD'));

    convertWithKey('secret-key')
        ->assertHasErrors()
        ->assertDontSee('secret-key');
});
