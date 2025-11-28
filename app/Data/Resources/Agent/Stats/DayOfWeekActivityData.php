<?php

namespace App\Data\Resources\Agent\Stats;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class DayOfWeekActivityData extends Data
{
    public function __construct(
        public string $day,
        public int $day_number,
        public int $count,
    ) {}
}
