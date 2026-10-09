<?php

namespace Domain\Agent\Data\Resources\Stats;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Conversations started on one day of the week (UTC), all time.
 */
#[TypeScript]
class DayOfWeekActivityData extends Data
{
    public function __construct(
        public string $day,
        public int $day_number,
        public int $count,
    ) {}
}
