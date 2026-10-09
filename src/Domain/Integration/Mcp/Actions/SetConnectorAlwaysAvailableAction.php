<?php

namespace Domain\Integration\Mcp\Actions;

use Domain\Agent\Models\Agent;
use Domain\Integration\Mcp\Data\Agent\SetConnectorAlwaysAvailableData;
use Domain\Integration\Mcp\Exceptions\AlwaysAvailableToolLimitException;
use Domain\Integration\Mcp\Exceptions\ConnectorNotAttachedException;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Domain\Integration\Tool\Data\ToolEntry;
use Domain\Integration\Tool\Registry\ToolRegistry;

/**
 * Marks a connector attached to an agent as "always on": its tools ship on
 * every turn, bypassing the relevance router. Because every always-on tool
 * rides along on every reply, the agent's always-on connectors may together
 * expose at most MAX_TOOLS tools — counted from the live tool pool, so
 * blocked tools don't count and a connector's catalog growing later is
 * caught by the selector's own cap.
 */
class SetConnectorAlwaysAvailableAction
{
    public const int MAX_TOOLS = 10;

    public function __construct(private readonly ToolRegistry $registry) {}

    public function execute(Agent $agent, AgentMcpServer $server, SetConnectorAlwaysAvailableData $data): void
    {
        if (! $agent->mcpServers()->whereKey($server->id)->exists()) {
            throw ConnectorNotAttachedException::toAgent((string) $server->slug, $agent->id);
        }

        if ($data->always_available) {
            $slugs = $agent->mcpServers()
                ->wherePivot('always_available', true)
                ->whereKeyNot($server->id)
                ->pluck('mcp_servers.slug')
                ->push($server->slug)
                ->all();

            $wouldBe = count(array_filter(
                $this->registry->for($agent),
                static fn (ToolEntry $entry): bool => $entry->isFromAnyServer($slugs),
            ));

            if ($wouldBe > self::MAX_TOOLS) {
                throw AlwaysAvailableToolLimitException::reached(self::MAX_TOOLS, $wouldBe);
            }
        }

        $agent->mcpServers()->updateExistingPivot($server->id, ['always_available' => $data->always_available]);
    }
}
