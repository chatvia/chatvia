<?php

declare(strict_types=1);

namespace Domain\Integration\Connector\Catalog\Connectors;

use Domain\Integration\Connector\Catalog\DynamicOAuthConnector;
use Domain\Integration\Connector\Enums\ConnectorCategory;

/**
 * Pipedrive's hosted MCP server (note the `.ai` host); OAuth with dynamic client registration.
 */
final class PipedriveConnector extends DynamicOAuthConnector
{
    #[\Override]
    public string $slug { get => 'pipedrive'; }

    #[\Override]
    public string $name { get => 'Pipedrive'; }

    #[\Override]
    public string $description { get => 'Read and update deals, people, leads, activities, and notes in your Pipedrive CRM.'; }

    #[\Override]
    public ConnectorCategory $category { get => ConnectorCategory::Sales; }

    #[\Override]
    public string $brandColor { get => '017737'; }

    #[\Override]
    public int $priority { get => 46; }

    #[\Override]
    public string $mcpUrl { get => 'https://mcp.pipedrive.ai/mcp'; }
}
