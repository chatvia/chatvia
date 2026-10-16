<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Analytics;

use Domain\Analytics\Data\MessageDimensionBreakdownData;
use Spatie\LaravelData\Data;

/**
 * Daily breakdown of one message dimension (sentiment or tone) over the
 * public API. Row keys are the dimension's values plus `unspecified`
 * (visitor messages that were never analysed).
 */
class DimensionBreakdownData extends Data
{
    public function __construct(
        /**
         * One row per calendar day: `date` plus a count per dimension value.
         *
         * @var list<array<string, int|string>>
         */
        public array $by_date,
        /**
         * Each dimension value summed over the range, plus `unspecified` and
         * `total` (all visitor messages).
         *
         * @var array<string, int>
         */
        public array $totals,
    ) {}

    public static function fromBreakdown(MessageDimensionBreakdownData $breakdown): self
    {
        return new self(by_date: $breakdown->byDate, totals: $breakdown->totals);
    }
}
