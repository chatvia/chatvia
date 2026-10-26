<?php

declare(strict_types=1);

namespace Domain\Integration\Connector\Actions\Agent;

use Domain\Agent\Models\Agent;
use Domain\Integration\Connector\Data\Catalog\ConnectorData;
use Domain\Integration\Connector\Exceptions\CapabilityProviderUnavailableException;
use Domain\Integration\Connector\Exceptions\CapabilityRequiresConnectorException;
use Domain\Integration\Connector\Exceptions\CapabilityUnavailableException;
use Domain\Integration\Connector\Services\ConnectorCatalog;
use Domain\Integration\Mcp\Exceptions\McpException;
use Domain\Integration\Mcp\Models\AgentMcpServer;

/**
 * A connector was attached to an agent: turn on, for that agent, every
 * capability that works through it (attaching Calendly or Cal.com turns
 * Calendar on), and make it the connector those capabilities use. A
 * capability the agent already has stays on; only its connector changes.
 * The connector's own tools stay attached. A capability that cannot be turned
 * on (the connection is failing, its tools cannot be synced) is left off and
 * reported; the attach itself still succeeds.
 */
class EnableCapabilitiesThroughConnectorAction
{
    public function __construct(
        private readonly ConnectorCatalog $catalog,
        private readonly EnableAgentCapabilityAction $enable,
        private readonly ChooseCapabilityProviderAction $choose,
    ) {}

    public function execute(Agent $agent, AgentMcpServer $server): void
    {
        if (blank($server->connector_slug)) {
            return;
        }

        $capabilities = $this->catalog->capabilities()
            ->filter(fn (ConnectorData $capability): bool => in_array($server->connector_slug, $capability->worksThrough, true));

        foreach ($capabilities as $capability) {
            try {
                $this->enable->execute($agent, $capability);
                $this->choose->execute($agent, $capability, $server->connector_slug);
            } catch (CapabilityRequiresConnectorException|CapabilityProviderUnavailableException|CapabilityUnavailableException) {
                // The connection is switched off or failing, or the install has
                // no license key for capabilities: nothing to turn on yet.
            } catch (McpException $e) {
                report($e);
            }
        }
    }
}
