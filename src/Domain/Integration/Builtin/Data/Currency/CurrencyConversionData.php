<?php

declare(strict_types=1);

namespace Domain\Integration\Builtin\Data\Currency;

/**
 * The result of one conversion: the converted amount and the cross rate used
 * (1 {@see $from} = {@see $rate} {@see $to}), both unrounded.
 */
final readonly class CurrencyConversionData
{
    public function __construct(
        public string $from,
        public string $to,
        public float $amount,
        public float $converted,
        public float $rate,
    ) {}
}
