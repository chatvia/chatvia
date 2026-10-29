<?php

declare(strict_types=1);

use Domain\Integration\Builtin\Data\Currency\CurrencyConversionRequestData;
use Domain\Integration\Builtin\Exceptions\CurrencyConversionException;
use Domain\Integration\Builtin\Services\CurrencyConverter;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;

/**
 * Currency conversion over ExchangeRate-API with the workspace's own key:
 * any pair through the USD table, and the table cached per key (a bad key
 * never rides on another key's cache), failing with a message that never
 * carries the key.
 */
beforeEach(function (): void {
    config(['services.exchange_rate.base_url' => 'https://rates.example.test/v6']);

    Http::fake(fn (ClientRequest $request) => match ($request->header('Authorization')[0] ?? null) {
        'Bearer good-key' => Http::response([
            'result' => 'success',
            'conversion_rates' => ['USD' => 1, 'EUR' => 0.5, 'JPY' => 150],
        ]),
        default => Http::response(['result' => 'error'], 403),
    });
});

it('converts across the USD table', function (): void {
    $result = app(CurrencyConverter::class)->convert('good-key', new CurrencyConversionRequestData('EUR', 'JPY', 10));

    expect($result->from)->toBe('EUR')
        ->and($result->to)->toBe('JPY')
        ->and($result->amount)->toBe(10.0)
        ->and($result->converted)->toBe(3000.0)
        ->and($result->rate)->toEqual(300);
});

it('caches the rate table per API key', function (): void {
    $converter = app(CurrencyConverter::class);

    $converter->convert('good-key', new CurrencyConversionRequestData('USD', 'EUR', 1));
    $converter->convert('good-key', new CurrencyConversionRequestData('USD', 'JPY', 1));

    expect(fn () => $converter->convert('bad-key', new CurrencyConversionRequestData('USD', 'EUR', 1)))
        ->toThrow(CurrencyConversionException::class, 'Could not fetch exchange rates — please try again.');

    Http::assertSentCount(2);
});
