<?php

declare(strict_types=1);

namespace Domain\Integration\Connector\Catalog\Connectors;

use Domain\Integration\Connector\Catalog\DynamicOAuthConnector;
use Domain\Integration\Connector\Enums\ConnectorCategory;

/**
 * Attio's hosted MCP server; OAuth with dynamic client registration. It acts with the
 * signed-in person's Attio permissions.
 */
final class AttioConnector extends DynamicOAuthConnector
{
    #[\Override]
    public string $slug { get => 'attio'; }

    #[\Override]
    public string $name { get => 'Attio'; }

    #[\Override]
    public string $description { get => 'Search and update records, lists, notes, and tasks in your Attio CRM.'; }

    #[\Override]
    public ConnectorCategory $category { get => ConnectorCategory::Sales; }

    #[\Override]
    public string $brandColor { get => '0C0C0D'; }

    #[\Override]
    public int $priority { get => 47; }

    #[\Override]
    public string $mcpUrl { get => 'https://mcp.attio.com/mcp'; }
}
