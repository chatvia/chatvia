<?php

namespace Domain\Integration\Mcp\Services\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Throwable;

#[Description('Convert an amount between currencies using current exchange rates. Source defaults to USD.')]
#[IsReadOnly]
#[IsIdempotent]
#[IsOpenWorld]
class ExchangeRateTool extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'to' => ['required', 'string', 'size:3'],
            'from' => ['nullable', 'string', 'size:3'],
            'amount' => ['nullable', 'numeric', 'min:0'],
        ], [
            'to.required' => 'You must specify the target currency as a 3-letter ISO code, e.g. "EUR", "JPY", "GBP".',
            'to.size' => 'The "to" currency must be a 3-letter ISO code, e.g. "EUR".',
            'from.size' => 'The "from" currency must be a 3-letter ISO code, e.g. "USD".',
        ]);

        $to = strtoupper($validated['to']);
        $from = strtoupper($validated['from'] ?? 'USD');
        $amount = (float) ($validated['amount'] ?? 1);

        // The customer supplies their own ExchangeRate-API key when they connect
        // this connector; McpClient forwards it as a Bearer token.
        $apiKey = app(HttpRequest::class)->bearerToken();
        if (! $apiKey) {
            return Response::error('No ExchangeRate-API key was provided. Reconnect the Currency Converter connector with a valid key.');
        }

        $rates = $this->usdRates($apiKey);

        if ($rates === null) {
            return Response::error('Could not fetch exchange rates — please try again.');
        }

        if (! isset($rates[$to])) {
            return Response::error("Unknown currency code: {$to}.");
        }
        if (! isset($rates[$from])) {
            return Response::error("Unknown currency code: {$from}.");
        }

        // Cross-rate via USD: amount(from) → USD → to.
        $amountInUsd = $amount / $rates[$from];
        $converted = $amountInUsd * $rates[$to];
        $rate = $rates[$to] / $rates[$from];

        $text = sprintf(
            '%s %s = %s %s (1 %s = %s %s).',
            number_format($amount, 2),
            $from,
            number_format($converted, 4),
            $to,
            $from,
            number_format($rate, 6),
            $to,
        );

        return Response::make(Response::text($text))->withStructuredContent([
            'from' => $from,
            'to' => $to,
            'amount' => $amount,
            'converted' => round($converted, 6),
            'rate' => round($rate, 6),
        ]);
    }

    /**
     * The USD rate table for this API key, cached for an hour. The cache is
     * per key (by hash) so one workspace's key never answers for another's —
     * an invalid or over-quota key must fail on its own.
     *
     * @return array<string, float|int>|null
     */
    private function usdRates(string $apiKey): ?array
    {
        $cacheKey = 'exchange_rates:USD:'.hash('sha256', $apiKey);

        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        // The provider takes the key in the URL path. Connection errors carry
        // that URL in their message, so they are swallowed here rather than
        // surfaced to the model or the logs.
        $baseUrl = rtrim((string) config('services.exchange_rate.base_url'), '/');

        try {
            $response = Http::timeout(10)->get($baseUrl.'/'.rawurlencode($apiKey).'/latest/USD');
        } catch (Throwable) {
            return null;
        }

        $body = $response->successful() ? $response->json() : null;
        $rates = is_array($body) && ($body['result'] ?? null) === 'success' && is_array($body['conversion_rates'] ?? null)
            ? $body['conversion_rates']
            : null;

        if ($rates !== null) {
            Cache::put($cacheKey, $rates, now()->addHour());
        }

        return $rates;
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'to' => $schema->string()
                ->description('Target currency as a 3-letter ISO code, e.g. "EUR", "JPY", "GBP".')
                ->required(),

            'from' => $schema->string()
                ->description('Source currency as a 3-letter ISO code (default "USD").')
                ->default('USD'),

            'amount' => $schema->number()
                ->description('Amount in the source currency to convert (default 1).')
                ->default(1),
        ];
    }
}
