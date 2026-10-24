<?php

declare(strict_types=1);

namespace Domain\Integration\Connector\Catalog\Connectors;

use Domain\Integration\Connector\Catalog\DynamicOAuthConnector;
use Domain\Integration\Connector\Enums\ConnectorCategory;

/**
 * Calendly's hosted MCP server; OAuth with dynamic client registration.
 */
final class CalendlyConnector extends DynamicOAuthConnector
{
    #[\Override]
    public string $slug { get => 'calendly'; }

    #[\Override]
    public string $name { get => 'Calendly'; }

    #[\Override]
    public string $description { get => 'Let visitors book meetings on your Calendly, and let agents read your scheduled events.'; }

    #[\Override]
    public ConnectorCategory $category { get => ConnectorCategory::Productivity; }

    #[\Override]
    public string $brandColor { get => '006BFF'; }

    #[\Override]
    public int $priority { get => 25; }

    #[\Override]
    public string $mcpUrl { get => 'https://mcp.calendly.com'; }
}
