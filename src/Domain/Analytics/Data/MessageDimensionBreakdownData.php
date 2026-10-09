<?php

declare(strict_types=1);

namespace Domain\Analytics\Data;

use Spatie\LaravelData\Data;

/**
 * Daily breakdown of one message dimension (sentiment or tone) for an agent.
 *
 *  - `byDate`: one row per calendar day — `date` plus a count per bucket value
 *    and `unspecified` (visitor messages that were never analysed).
 *  - `totals`: each bucket summed over the range, plus `unspecified` and
 *    `total` (all visitor messages).
 *
 * The row keys are the dimension's values, so the rows stay plain arrays —
 * they go to the chart as-is.
 */
class MessageDimensionBreakdownData extends Data
{
    /**
     * @param  list<array<string, int|string>>  $byDate
     * @param  array<string, int>  $totals
     */
    public function __construct(
        public array $byDate,
        public array $totals,
    ) {}
}
