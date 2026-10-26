<?php

declare(strict_types=1);

namespace Domain\Integration\Connector\Listeners;

use Domain\Agent\Events\ConnectorAttachedToAgent;
use Domain\Agent\Models\Agent;
use Domain\Integration\Connector\Actions\Agent\EnableCapabilitiesThroughConnectorAction;
use Domain\Integration\Mcp\Models\AgentMcpServer;

/**
 * Turn on the capabilities that work through a connector just attached to an
 * agent (Calendar for Calendly or Cal.com). Runs in the request, so the
 * Capabilities page shows it right away.
 */
class EnableCapabilitiesThroughConnector
{
    public function __construct(private readonly EnableCapabilitiesThroughConnectorAction $enable) {}

    public function handle(ConnectorAttachedToAgent $event): void
    {
        $agent = Agent::query()->find($event->agentId);
        $server = AgentMcpServer::query()->find($event->mcpServerId);

        if ($agent instanceof Agent && $server instanceof AgentMcpServer) {
            $this->enable->execute($agent, $server);
        }
    }
}
