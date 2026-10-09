<?php

namespace Domain\Integration\Builtin\Exceptions;

use RuntimeException;

/**
 * A conversion that could not be done. The message is written for the model
 * to read and never contains the API key.
 */
final class CurrencyConversionException extends RuntimeException
{
    public static function ratesUnavailable(): self
    {
        return new self('Could not fetch exchange rates — please try again.');
    }

    public static function unknownCurrency(string $code): self
    {
        return new self("Unknown currency code: {$code}.");
    }
}
