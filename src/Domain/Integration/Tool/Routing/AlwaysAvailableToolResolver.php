<?php

namespace Domain\Integration\Tool\Routing;

use Domain\Agent\Models\Agent;
use Domain\Integration\Mcp\Actions\SetConnectorAlwaysAvailableAction;
use Domain\Integration\Tool\Data\ToolEntry;

/**
 * The tools an agent ships on EVERY turn regardless of what the relevance
 * router thinks of the message: those of connectors the workspace marked
 * "always on" for this agent (`agent_mcp_server.always_available`).
 *
 * A dedicated tutor or CRM agent wants its own tools present even on a turn
 * like "hi" or "how do you say hello", where a reranker scoring tool
 * descriptions against the message would drop them. Pinning trades a few
 * hundred tokens per turn for that certainty, which is why the set is capped
 * at SetConnectorAlwaysAvailableAction::MAX_TOOLS — enforced when pinning,
 * and re-applied here in case a pinned connector's catalog grew afterwards.
 */
class AlwaysAvailableToolResolver
{
    /**
     * @param  array<int, ToolEntry>  $pool  The agent's full tool pool.
     * @return array<int, ToolEntry>
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

        $pinned = array_values(array_filter(
            $pool,
            static fn (ToolEntry $entry): bool => $entry->isFromAnyServer($slugs),
        ));

        return array_slice($pinned, 0, SetConnectorAlwaysAvailableAction::MAX_TOOLS);
    }
}
