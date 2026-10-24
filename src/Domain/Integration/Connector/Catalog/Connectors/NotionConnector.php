<?php

declare(strict_types=1);

namespace Domain\Integration\Connector\Catalog\Connectors;

use Domain\Integration\Connector\Catalog\DynamicOAuthConnector;
use Domain\Integration\Connector\Enums\ConnectorCategory;

/**
 * Notion's hosted MCP server; OAuth with dynamic client registration.
 */
final class NotionConnector extends DynamicOAuthConnector
{
    #[\Override]
    public string $slug { get => 'notion'; }

    #[\Override]
    public string $name { get => 'Notion'; }

    #[\Override]
    public string $description { get => 'Read pages, databases, and comments from your Notion workspace.'; }

    #[\Override]
    public ConnectorCategory $category { get => ConnectorCategory::Productivity; }

    #[\Override]
    public string $brandColor { get => '000000'; }

    #[\Override]
    public int $priority { get => 50; }

    #[\Override]
    public string $mcpUrl { get => 'https://mcp.notion.com/mcp'; }
}
