<?php

declare(strict_types=1);

namespace App\Http\Controllers\Agent\Source\File\Manage;

use Domain\Agent\Data\Resources\Sources\AgentSourcesStatsData;
use Domain\Agent\Models\Agent;
use Domain\Agent\Queries\AgentSourceCountsQuery;

/**
 * Returns the counts shown on the agent's knowledge overview (trained files,
 * websites, texts, …).
 */
class AgentKnowledgeSourcesStatsController
{
    public function __invoke(Agent $agent, AgentSourceCountsQuery $counts): AgentSourcesStatsData
    {
        return $counts->get($agent);
    }
}
