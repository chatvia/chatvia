<?php

namespace Domain\Integration\Tool\Routing;

use Domain\Agent\Models\Agent;
use Domain\Integration\Tool\Data\ToolEntryData;

/**
 * The tools an agent ships on EVERY turn regardless of what the relevance
 * router thinks of the message: those of connectors the workspace marked
 * "always on" for this agent (`agent_mcp_server.always_available`).
 *
 * A dedicated tutor or CRM agent wants its own tools present even on a turn
 * like "hi" or "how do you say hello", where a reranker scoring tool
 * descriptions against the message would drop them. Every pinned tool
 * ships, however many there are: the workspace chose to pay the extra
 * tokens per turn for that certainty.
 */
class AlwaysAvailableToolResolver
{
    /**
     * @param  array<int, ToolEntryData>  $pool  The agent's full tool pool.
     * @return array<int, ToolEntryData>
     */
    public function resolve(Agent $agent, array $pool): array
    {
        $slugs = $agent->mcpServers()
            ->where('is_enabled', true)
            ->wherePivot('always_available', true)
            ->pluck('mcp_servers.slug')
            ->all();

        if ($slugs === []) {
            return [];
        }

        return array_values(array_filter(
            $pool,
            static fn (ToolEntryData $entry): bool => $entry->isFromAnyServer($slugs),
        ));
    }
}
