<?php

declare(strict_types=1);

namespace Domain\Integration\Connector\Catalog\Connectors;

use Domain\Integration\Connector\Catalog\DynamicOAuthConnector;
use Domain\Integration\Connector\Enums\ConnectorCategory;

/**
 * Cal.com's hosted MCP server; OAuth with dynamic client registration. Only
 * cal.com accounts: self-hosted Cal.com and the cal.eu region have no hosted
 * server.
 */
final class CalcomConnector extends DynamicOAuthConnector
{
    #[\Override]
    public string $slug { get => 'cal-com'; }

    #[\Override]
    public string $name { get => 'Cal.com'; }

    #[\Override]
    public string $description { get => 'Let visitors book meetings on your Cal.com, and let agents read your bookings.'; }

    #[\Override]
    public ConnectorCategory $category { get => ConnectorCategory::Productivity; }

    #[\Override]
    public string $brandColor { get => '292929'; }

    #[\Override]
    public int $priority { get => 26; }

    #[\Override]
    public string $mcpUrl { get => 'https://mcp.cal.com/mcp'; }
}
