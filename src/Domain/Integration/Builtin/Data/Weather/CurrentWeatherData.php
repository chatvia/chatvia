<?php

declare(strict_types=1);

namespace Domain\Integration\Builtin\Data\Weather;

use Domain\Integration\Builtin\Enums\WeatherUnit;

/**
 * The current weather in one city, as OpenWeatherMap reports it. Numbers keep
 * the provider's own int/float form so they reach the model unchanged.
 */
final readonly class CurrentWeatherData
{
    public function __construct(
        public string $city,
        /** ISO country code, or '' when the provider omits it. */
        public string $country,
        public int|float $temperature,
        public int|float $feelsLike,
        public int|float $humidity,
        /** Wind speed, or null when the provider omits it. */
        public int|float|null $wind,
        /** A short description such as "light rain", or 'n/a'. */
        public string $conditions,
        public WeatherUnit $units,
    ) {}
}
