<?php

namespace App\Data\Resources\Agent\Stats;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class MessageByDayData extends Data
{
    public function __construct(
        public string $day,
        public string $date,
        public int $value,
    ) {}
}
