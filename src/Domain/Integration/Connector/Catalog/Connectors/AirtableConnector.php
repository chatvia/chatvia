<?php

declare(strict_types=1);

namespace Domain\Integration\Connector\Catalog\Connectors;

use Domain\Integration\Connector\Catalog\DynamicOAuthConnector;
use Domain\Integration\Connector\Enums\ConnectorCategory;

/**
 * Airtable's hosted MCP server; OAuth with dynamic client registration.
 */
final class AirtableConnector extends DynamicOAuthConnector
{
    #[\Override]
    public string $slug { get => 'airtable'; }

    #[\Override]
    public string $name { get => 'Airtable'; }

    #[\Override]
    public string $description { get => 'Read and write bases, tables, and records in Airtable.'; }

    #[\Override]
    public ConnectorCategory $category { get => ConnectorCategory::Productivity; }

    #[\Override]
    public string $brandColor { get => '18BFFF'; }

    #[\Override]
    public int $priority { get => 67; }

    #[\Override]
    public string $mcpUrl { get => 'https://mcp.airtable.com/mcp'; }
}
