<?php

namespace App\Data\Resources\Agent\Stats;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

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
