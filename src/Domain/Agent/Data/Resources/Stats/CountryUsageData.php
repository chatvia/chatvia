<?php

declare(strict_types=1);

namespace Domain\Agent\Data\Resources\Stats;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Conversations from one visitor country and its share of all conversations
 * with a known country.
 */
#[TypeScript]
class CountryUsageData extends Data
{
    public function __construct(
        public string $country_code,
        public string $country_name,
        public int $count,
        public float $percentage,
    ) {}
}
