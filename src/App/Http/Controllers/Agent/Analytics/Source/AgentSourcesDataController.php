<?php

namespace App\Http\Controllers\Agent\Analytics\Source;

use Domain\Agent\Models\Agent;
use Domain\Analytics\Data\Agent\Usage\SourceUsageResourceData;
use Domain\Analytics\Queries\Agent\AgentKnowledgeUsageQuery;
use Domain\Analytics\Queries\Agent\AgentToolUsageQuery;

/**
 * The knowledge sources, functions and MCP tools the agent used most, with
 * their last use, for the source-usage analytics page.
 */
class AgentSourcesDataController
{
    public function __invoke(Agent $agent, AgentKnowledgeUsageQuery $knowledge, AgentToolUsageQuery $tools): SourceUsageResourceData
    {
        return SourceUsageResourceData::fromUsage(
            knowledge: $knowledge->sources($agent),
            functions: $tools->functions($agent),
            mcpTools: $tools->mcpTools($agent),
        );
    }
}
