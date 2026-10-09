<?php

namespace Domain\Integration\Builtin\Enums;

/**
 * The unit system a weather lookup reports in. The backing values are the
 * ones OpenWeatherMap's `units` parameter takes.
 */
enum WeatherUnits: string
{
    case Metric = 'metric';
    case Imperial = 'imperial';

    public function temperatureSymbol(): string
    {
        return match ($this) {
            self::Metric => '°C',
            self::Imperial => '°F',
        };
    }

    public function windSpeedUnit(): string
    {
        return match ($this) {
            self::Metric => 'm/s',
            self::Imperial => 'mph',
        };
    }
}
