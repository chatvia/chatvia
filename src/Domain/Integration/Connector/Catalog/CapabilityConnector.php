<?php

declare(strict_types=1);

namespace Domain\Integration\Connector\Catalog;

use Domain\Integration\Connector\Enums\ConnectorAuthStrategy;
use Domain\Integration\Mcp\Enums\McpServerCategory;
use Support\License\Entitlements;

/**
 * A first-party, no-auth chat enrichment (Interactive charts, Forms) hosted by
 * this app. It is listed on an agent's Capabilities page instead of the
 * Connectors page; underneath it is an ordinary builtin MCP server that the
 * workspace connects once and enables per agent.
 *
 * Every capability needs a license key: without one it is unavailable, so
 * agents cannot turn it on and its tools are withheld from their chats.
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
    public function isAvailable(): bool
    {
        return app(Entitlements::class)->capabilities();
    }

    #[\Override]
    protected function extraData(): array
    {
        return ['isCapability' => true, 'needsLicense' => true];
    }
}
