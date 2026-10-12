<?php

declare(strict_types=1);

namespace Domain\Analytics\Data\Dashboard;

use Spatie\LaravelData\Data;

/**
 * The dashboard home numbers that come from the analytics tables, for one
 * workspace up to today: all-time conversations, visitors of the last 24
 * hours, AI replies per day for the last 7 and 30 days, and AI replies this
 * month and last month. Cached as one value by GetDashboardStatsAction.
 */
class DashboardActivityData extends Data
{
    /**
     * @param  list<DailyVolumeData>  $weeklyUsage
     * @param  list<DailyVolumeData>  $monthlyUsage
     */
    public function __construct(
        public int $conversations,
        public int $activeSessions,
        /** @var list<DailyVolumeData> */
        public array $weeklyUsage,
        /** @var list<DailyVolumeData> */
        public array $monthlyUsage,
        public int $repliesThisMonth,
        public int $repliesLastMonth,
    ) {}
}
