<?php

declare(strict_types=1);

namespace Domain\Integration\Connector\Catalog\Connectors;

use Domain\Integration\Connector\Catalog\DynamicOAuthConnector;
use Domain\Integration\Connector\Enums\ConnectorCategory;

/**
 * Help Scout's hosted MCP server; OAuth with dynamic client registration. Read-only, and needs
 * a plan with API access.
 */
final class HelpScoutConnector extends DynamicOAuthConnector
{
    #[\Override]
    public string $slug { get => 'help-scout'; }

    #[\Override]
    public string $name { get => 'Help Scout'; }

    #[\Override]
    public string $description { get => 'Search Help Scout conversations and pull support reports.'; }

    #[\Override]
    public ConnectorCategory $category { get => ConnectorCategory::Support; }

    #[\Override]
    public string $brandColor { get => '1292EE'; }

    #[\Override]
    public int $priority { get => 34; }

    #[\Override]
    public string $mcpUrl { get => 'https://mcp.helpscout.net/mcp'; }
}
