<?php

namespace Domain\Integration\Builtin\Services;

use Domain\Integration\Builtin\Data\Currency\CurrencyConversionData;
use Domain\Integration\Builtin\Data\Currency\CurrencyConversionRequestData;
use Domain\Integration\Builtin\Exceptions\CurrencyConversionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * Converts between any two currencies with ExchangeRate-API, using the
 * workspace's own API key (the Currency Converter connector is
 * bring-your-own-key). One USD rate table per key is cached for an hour and
 * every pair is converted across it.
 */
class CurrencyConverter
{
    private const string BASE_CURRENCY = 'USD';

    /**
     * @throws CurrencyConversionException
     */
    public function convert(string $apiKey, CurrencyConversionRequestData $request): CurrencyConversionData
    {
        $rates = $this->usdRates($apiKey);

        if ($rates === null) {
            throw CurrencyConversionException::ratesUnavailable();
        }

        if (! isset($rates[$request->to])) {
            throw CurrencyConversionException::unknownCurrency($request->to);
        }
        if (! isset($rates[$request->from])) {
            throw CurrencyConversionException::unknownCurrency($request->from);
        }

        // Cross-rate via USD: amount(from) → USD → to.
        $amountInUsd = $request->amount / $rates[$request->from];

        return new CurrencyConversionData(
            from: $request->from,
            to: $request->to,
            amount: $request->amount,
            converted: $amountInUsd * $rates[$request->to],
            rate: $rates[$request->to] / $rates[$request->from],
        );
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
        $cacheKey = 'exchange_rates:'.self::BASE_CURRENCY.':'.hash('sha256', $apiKey);

        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        // The key goes in a bearer header, never in the URL, so it cannot leak
        // through request logs or error messages. Connection errors are still
        // swallowed: the model only needs to know the rates are unavailable.
        $baseUrl = Str::rtrim((string) config('services.exchange_rate.base_url'), '/');

        try {
            $response = Http::timeout(10)
                ->withToken($apiKey)
                ->get($baseUrl.'/latest/'.self::BASE_CURRENCY);
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
}
