<?php

namespace Domain\Analytics\Data;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Spatie\LaravelData\Data;

/**
 * Inclusive [start, end] date range for analytics queries.
 *
 *  - `start` is at 00:00 of its day
 *  - `end` is at 23:59:59 of its day
 *
 * `fromRequestOrLastDays()` is the canonical constructor used by the
 * analytics controllers (Tone, Sentiment, Overview, …). It replaces the
 * historical inline expression `now()->subDays(6)->startOfDay()` that was
 * duplicated across 6 files. The default of 7 days INCLUSIVE matches the
 * legacy expression (today + the previous 6 days = 7 buckets).
 */
class DateRangeData extends Data
{
    /**
     * Default range length in days, INCLUSIVE — today plus the previous 6
     * gives 7 buckets, matching the legacy `now()->subDays(6)->startOfDay()`.
     */
    private const DEFAULT_DAYS = 7;

    public function __construct(
        public Carbon $start,
        public Carbon $end,
    ) {}

    public static function fromRequestOrLastDays(Request $request, int $days = self::DEFAULT_DAYS): self
    {
        $start = $request->input('start_date')
            ? Carbon::parse((string) $request->input('start_date'))->startOfDay()
            : now()->subDays(max(0, $days - 1))->startOfDay();

        $end = $request->input('end_date')
            ? Carbon::parse((string) $request->input('end_date'))->endOfDay()
            : now()->endOfDay();

        return new self($start, $end);
    }

    /**
     * Canonical default range start for callers with no explicit `start_date`:
     * the first day (at 00:00) of the inclusive default window.
     */
    public static function defaultStart(): Carbon
    {
        return now()->subDays(self::DEFAULT_DAYS - 1)->startOfDay();
    }
}
