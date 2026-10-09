<?php

namespace Domain\Analytics\Data\Dashboard;

use Spatie\LaravelData\Data;

/**
 * Everything the dashboard home page shows, for one workspace: headline
 * counts, the message-credit panel, knowledge sources, daily reply volume and
 * plan usage. Property names are the JSON keys the page reads.
 */
class DashboardStatsData extends Data
{
    /**
     * @param  list<DailyVolumeData>  $weekly_usage
     * @param  list<DailyVolumeData>  $monthly_usage
     */
    public function __construct(
        public AgentCountData $agents,
        public MessagesPanelData $messages,
        public int $conversations,
        public int $active_sessions,
        public KnowledgeSourcesStatsData $knowledge_sources,
        public TeamSizeData $team,
        /** @var list<DailyVolumeData> */
        public array $weekly_usage,
        /** @var list<DailyVolumeData> */
        public array $monthly_usage,
        public UsageStatsData $usage,
    ) {}
}
