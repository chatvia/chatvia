<?php

namespace App\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Laravel\Mcp\Server\Tool;

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

        $apiKey = config('services.exchange_rate.key');
        if (! $apiKey) {
            return Response::error('Exchange-rate API key is not configured on the server.');
        }

        // Rates change once daily — cache by base currency for an hour.
        $rates = Cache::remember(
            'exchange_rates:USD',
            now()->addHour(),
            function () use ($apiKey) {
                $response = Http::timeout(10)
                    ->get("https://v6.exchangerate-api.com/v6/{$apiKey}/latest/USD");

                if ($response->failed()) {
                    return null;
                }
                $body = $response->json();

                return ($body['result'] ?? null) === 'success' ? $body['conversion_rates'] : null;
            }
        );

        if ($rates === null) {
            Cache::forget('exchange_rates:USD');

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
     * Get the tool's input schema.
     *
     * @return array<string, JsonSchema>
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
