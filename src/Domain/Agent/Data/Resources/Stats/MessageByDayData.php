<?php

declare(strict_types=1);

namespace Domain\Agent\Data\Resources\Stats;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One day of the selected range on the Analytics overview: AI replies sent.
 */
#[TypeScript]
class MessageByDayData extends Data
{
    public function __construct(
        public string $day,
        public string $date,
        public int $value,
    ) {}
}
