<?php

declare(strict_types=1);

namespace Domain\Integration\Connector\Services;

use Domain\Agent\Models\Agent;
use Domain\Integration\Mcp\Models\AgentMcpServer;

/**
 * The agent's workspace's connection to a catalog connector, when it can be
 * used: switched on and not failing. It need not be attached to the agent: a
 * capability that works through it (Calendar through Calendly) calls it from
 * the app, so the model never sees the connector's own tools.
 */
class WorkspaceConnection
{
    public function serverFor(Agent $agent, string $connectorSlug): ?AgentMcpServer
    {
        return AgentMcpServer::query()
            ->ownedBySameAccountAs($agent)
            ->where('connector_slug', $connectorSlug)
            ->where('is_enabled', true)
            ->whereNull('last_error')
            ->first();
    }

    /**
     * Of the given connector slugs, the ones the workspace can use, in the
     * given order.
     *
     * @param  list<string>  $connectorSlugs
     * @return list<string>
     */
    public function connected(Agent $agent, array $connectorSlugs): array
    {
        if ($connectorSlugs === []) {
            return [];
        }

        $usable = AgentMcpServer::query()
            ->ownedBySameAccountAs($agent)
            ->whereIn('connector_slug', $connectorSlugs)
            ->where('is_enabled', true)
            ->whereNull('last_error')
            ->pluck('connector_slug')
            ->all();

        return array_values(array_filter($connectorSlugs, fn (string $slug): bool => collect($usable)->containsStrict($slug)));
    }
}
