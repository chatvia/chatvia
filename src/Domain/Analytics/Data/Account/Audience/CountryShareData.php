<?php

namespace Domain\Analytics\Data\Account\Audience;

/**
 * One country's conversations and its share of the listed countries, in
 * percent. The code is upper-case ISO 3166-1 alpha-2.
 */
final readonly class CountryShareData
{
    public function __construct(
        public string $code,
        public string $name,
        public int $conversations,
        public float $pct,
    ) {}
}
