<?php

declare(strict_types=1);

namespace Domain\Integration\Connector\Catalog;

use Domain\Integration\Connector\Enums\ConnectorAuthStrategy;
use Domain\Integration\Mcp\Enums\McpServerCategory;

/**
 * A first-party, no-auth chat enrichment (Interactive charts, Forms) hosted by
 * this app. It is listed on an agent's Capabilities page instead of the
 * Connectors page; underneath it is an ordinary builtin MCP server that the
 * workspace connects once and enables per agent.
 */
abstract class CapabilityConnector extends Connector
{
    use HostsOnChatvia;

    #[\Override]
    public ConnectorAuthStrategy $authStrategy {
        get => ConnectorAuthStrategy::None;
    }

    #[\Override]
    public McpServerCategory $serverCategory {
        get => McpServerCategory::Builtin;
    }

    #[\Override]
    protected function extraData(): array
    {
        return ['isCapability' => true];
    }
}
