<?php

namespace Domain\Integration\Builtin\Data\Currency;

/**
 * One conversion to run: an amount in one currency, into another. Codes are
 * ISO 4217 and already upper-cased.
 */
final readonly class CurrencyConversionRequestData
{
    public function __construct(
        public string $from,
        public string $to,
        public float $amount,
    ) {}
}
