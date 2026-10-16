<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Analytics;

use App\Http\Resources\Api\V1\Analytics\SourceUsageReportData;
use Dedoc\Scramble\Attributes\Group;
use Domain\Agent\Models\Agent;
use Domain\Analytics\Queries\Agent\AgentKnowledgeUsageQuery;
use Domain\Analytics\Queries\Agent\AgentToolUsageQuery;

/**
 * Source and tool usage over the public API — the data behind the dashboard's
 * source-usage page, gated by `agent.analytics.sources`.
 */
#[Group(name: 'Analytics', weight: 140)]
class SourceUsageAnalyticsController
{
    /**
     * Get source usage
     *
     * The knowledge sources, webhook functions and MCP tools the agent used
     * most, all time, with their last use. Labels are recorded at call time,
     * so a deleted source keeps its real name.
     *
     * @return array{data: SourceUsageReportData}
     */
    public function __invoke(Agent $agent, AgentKnowledgeUsageQuery $knowledge, AgentToolUsageQuery $tools): array
    {
        return ['data' => SourceUsageReportData::fromUsage(
            knowledge: $knowledge->sources($agent),
            functions: $tools->functions($agent),
            mcpTools: $tools->mcpTools($agent),
        )];
    }
}
