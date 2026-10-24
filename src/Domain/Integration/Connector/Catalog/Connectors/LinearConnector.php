<?php

declare(strict_types=1);

namespace Domain\Integration\Connector\Catalog\Connectors;

use Domain\Integration\Connector\Catalog\DynamicOAuthConnector;
use Domain\Integration\Connector\Enums\ConnectorCategory;

/**
 * Linear's hosted MCP server; OAuth with dynamic client registration.
 */
final class LinearConnector extends DynamicOAuthConnector
{
    #[\Override]
    public string $slug { get => 'linear'; }

    #[\Override]
    public string $name { get => 'Linear'; }

    #[\Override]
    public string $description { get => 'Search issues, projects, and cycles across your Linear workspace.'; }

    #[\Override]
    public ConnectorCategory $category { get => ConnectorCategory::Productivity; }

    #[\Override]
    public string $brandColor { get => '5E6AD2'; }

    #[\Override]
    public int $priority { get => 80; }

    #[\Override]
    public string $mcpUrl { get => 'https://mcp.linear.app/mcp'; }
}
