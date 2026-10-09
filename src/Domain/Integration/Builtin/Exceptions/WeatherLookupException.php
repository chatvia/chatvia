<?php

namespace Domain\Integration\Builtin\Exceptions;

use RuntimeException;

/**
 * A weather lookup that produced no report. The message is written for the
 * model to read and never contains the API key or the key-bearing URL.
 */
final class WeatherLookupException extends RuntimeException
{
    public static function unreachable(): self
    {
        return new self('Could not reach the weather service — please try again.');
    }

    public static function cityNotFound(string $location): self
    {
        return new self("Couldn't find a city called \"{$location}\".");
    }

    public static function failed(int $status): self
    {
        return new self("Weather API returned HTTP {$status}.");
    }
}
