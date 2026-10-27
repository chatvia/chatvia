<?php

declare(strict_types=1);

namespace Domain\Analytics\Data\Dashboard;

use Spatie\LaravelData\Data;

/**
 * The dashboard home numbers that are cached, for one workspace up to today:
 * from the analytics tables all-time conversations, visitors of the last 24
 * hours, AI replies per day for the last 7 and 30 days, and AI replies this
 * month and last month; from the main tables the knowledge sources attached
 * to its agents and the size of the viewer's team. Cached as one value by
 * DashboardStatsQuery.
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
        public KnowledgeSourcesStatsData $knowledgeSources,
        public int $teamMembers,
    ) {}
}
