<?php

declare(strict_types=1);

namespace Domain\Booking\Services;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * How far a booking calendar reaches: one week of free times per request, at
 * most {@see MAX_DAYS_AHEAD} out, never the past. Limits what a visitor can
 * make the scheduler scan.
 */
final class BookingWindow
{
    public const int WINDOW_DAYS = 7;

    public const int MAX_DAYS_AHEAD = 31;

    /**
     * The range clamped to what may be asked: from a minute ahead (the
     * scheduler refuses the past) to a week later, never past the limit.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public static function clamp(CarbonInterface $from, CarbonInterface $to): array
    {
        $earliest = now()->toImmutable()->addMinute();
        $latest = now()->toImmutable()->addDays(self::MAX_DAYS_AHEAD);

        $from = $from->toImmutable()->max($earliest);
        $to = $to->toImmutable()->min($from->addDays(self::WINDOW_DAYS))->min($latest);

        return [$from->utc(), $to->utc()];
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} */
    public static function firstWeek(): array
    {
        return self::clamp(now(), now()->addDays(self::WINDOW_DAYS));
    }
}
