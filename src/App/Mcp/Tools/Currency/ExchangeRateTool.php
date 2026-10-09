<?php

namespace App\Mcp\Tools\Currency;

use App\Mcp\McpCallerContext;
use Domain\Integration\Builtin\Data\Currency\CurrencyConversionRequestData;
use Domain\Integration\Builtin\Exceptions\CurrencyConversionException;
use Domain\Integration\Builtin\Services\CurrencyConverter;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/**
 * The Currency Converter connector's only tool: convert an amount between two
 * currencies with the workspace's own ExchangeRate-API key.
 */
#[Description('Convert an amount between currencies using current exchange rates. Source defaults to USD.')]
#[IsReadOnly]
#[IsIdempotent]
#[IsOpenWorld]
class ExchangeRateTool extends Tool
{
    public function handle(Request $request, McpCallerContext $caller, CurrencyConverter $converter): Response|ResponseFactory
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

        $conversion = new CurrencyConversionRequestData(
            from: strtoupper($validated['from'] ?? 'USD'),
            to: strtoupper($validated['to']),
            amount: (float) ($validated['amount'] ?? 1),
        );

        // The customer supplies their own ExchangeRate-API key when they connect
        // this connector; McpClient forwards it as a Bearer token.
        if ($caller->connectorApiKey === null) {
            return Response::error('No ExchangeRate-API key was provided. Reconnect the Currency Converter connector with a valid key.');
        }

        try {
            $result = $converter->convert($caller->connectorApiKey, $conversion);
        } catch (CurrencyConversionException $e) {
            return Response::error($e->getMessage());
        }

        $text = sprintf(
            '%s %s = %s %s (1 %s = %s %s).',
            number_format($result->amount, 2),
            $result->from,
            number_format($result->converted, 4),
            $result->to,
            $result->from,
            number_format($result->rate, 6),
            $result->to,
        );

        return Response::make(Response::text($text))->withStructuredContent([
            'from' => $result->from,
            'to' => $result->to,
            'amount' => $result->amount,
            'converted' => round($result->converted, 6),
            'rate' => round($result->rate, 6),
        ]);
    }

    /**
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
