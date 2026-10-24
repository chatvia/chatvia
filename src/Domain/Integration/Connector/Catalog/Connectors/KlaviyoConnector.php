<?php

declare(strict_types=1);

namespace Domain\Integration\Connector\Catalog\Connectors;

use Domain\Integration\Connector\Catalog\DynamicOAuthConnector;
use Domain\Integration\Connector\Enums\ConnectorCategory;

/**
 * Klaviyo's hosted MCP server; OAuth with dynamic client registration. The person needs an
 * Owner, Admin or Manager role in Klaviyo.
 */
final class KlaviyoConnector extends DynamicOAuthConnector
{
    #[\Override]
    public string $slug { get => 'klaviyo'; }

    #[\Override]
    public string $name { get => 'Klaviyo'; }

    #[\Override]
    public string $description { get => 'Read profiles, lists, segments, campaigns, flows, and reports from Klaviyo.'; }

    #[\Override]
    public ConnectorCategory $category { get => ConnectorCategory::Marketing; }

    #[\Override]
    public string $brandColor { get => '232426'; }

    #[\Override]
    public int $priority { get => 97; }

    #[\Override]
    public string $mcpUrl { get => 'https://mcp.klaviyo.com/mcp'; }
}
