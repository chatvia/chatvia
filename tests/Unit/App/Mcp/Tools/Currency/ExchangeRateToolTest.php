<?php

declare(strict_types=1);

use App\Mcp\McpCallerContext;
use App\Mcp\Servers\CurrencyConverterServer;
use App\Mcp\Tools\Currency\ExchangeRateTool;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Tests\Support\Mcp\InternalMcp;

/**
 * The exchange-rate tool wraps ExchangeRate-API with the workspace's own key.
 * These snapshots pin both sides of each call: the exact request sent
 * upstream (the key only as a bearer token) and the exact MCP result the
 * model receives.
 */
beforeEach(function (): void {
    config(['services.exchange_rate.base_url' => 'https://rates.example.test/v6']);

    Http::fake(fn (ClientRequest $request) => match ($request->header('Authorization')[0] ?? null) {
        'Bearer rates-key' => Http::response([
            'result' => 'success',
            'conversion_rates' => ['USD' => 1, 'EUR' => 0.92, 'JPY' => 151.37],
        ]),
        default => Http::response(['result' => 'error'], 403),
    });
});

/** Act as a caller whose connector forwards this API key (or none). */
function ertCallerKey(?string $key): void
{
    app()->instance(McpCallerContext::class, new McpCallerContext(connectorApiKey: $key));
}

it('returns a stable currency result', function (array $arguments): void {
    ertCallerKey('rates-key');

    expect(InternalMcp::resultWithRequestsJson(CurrencyConverterServer::tool(ExchangeRateTool::class, $arguments)))->toMatchSnapshot();
})->with([
    'usd to eur, default amount' => [['to' => 'eur']],
    'eur to jpy with amount' => [['from' => 'EUR', 'to' => 'JPY', 'amount' => 250.5]],
    'unknown target' => [['to' => 'XXX']],
    'unknown source' => [['from' => 'ABC', 'to' => 'EUR']],
    'bad code length' => [['to' => 'EURO']],
]);

it('reports unavailable rates for a rejected key', function (): void {
    ertCallerKey('bad-key');

    expect(InternalMcp::resultWithRequestsJson(CurrencyConverterServer::tool(ExchangeRateTool::class, ['to' => 'EUR'])))->toMatchSnapshot();
});

it('refuses a conversion without an API key', function (): void {
    ertCallerKey(null);

    expect(InternalMcp::resultWithRequestsJson(CurrencyConverterServer::tool(ExchangeRateTool::class, ['to' => 'EUR'])))->toMatchSnapshot();
});
