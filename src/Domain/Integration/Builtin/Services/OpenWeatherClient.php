<?php

namespace Domain\Integration\Builtin\Services;

use Domain\Integration\Builtin\Data\Weather\CurrentWeatherData;
use Domain\Integration\Builtin\Enums\WeatherUnits;
use Domain\Integration\Builtin\Exceptions\WeatherLookupException;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Current-weather lookups against OpenWeatherMap, made with the workspace's
 * own API key (the Weather connector is bring-your-own-key).
 */
class OpenWeatherClient
{
    /**
     * @throws WeatherLookupException
     */
    public function current(string $apiKey, string $location, WeatherUnits $units): CurrentWeatherData
    {
        // The provider takes the key as a query parameter, and connection
        // errors carry the full URL in their message — keep that message away
        // from the model.
        try {
            $response = Http::timeout(10)->get($this->baseUrl().'/weather', [
                'q' => $location,
                'appid' => $apiKey,
                'units' => $units->value,
            ]);
        } catch (Throwable) {
            throw WeatherLookupException::unreachable();
        }

        if ($response->status() === 404) {
            throw WeatherLookupException::cityNotFound($location);
        }
        if ($response->failed()) {
            throw WeatherLookupException::failed($response->status());
        }

        $body = $response->json();

        return new CurrentWeatherData(
            city: $body['name'],
            country: $body['sys']['country'] ?? '',
            temperature: $body['main']['temp'],
            feelsLike: $body['main']['feels_like'],
            humidity: $body['main']['humidity'],
            wind: $body['wind']['speed'] ?? null,
            conditions: $body['weather'][0]['description'] ?? 'n/a',
            units: $units,
        );
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('services.openweather.base_url'), '/');
    }
}
