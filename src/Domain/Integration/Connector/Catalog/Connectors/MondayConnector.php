<?php

declare(strict_types=1);

namespace Domain\Integration\Connector\Catalog\Connectors;

use Domain\Integration\Connector\Catalog\DynamicOAuthConnector;
use Domain\Integration\Connector\Enums\ConnectorCategory;

/**
 * monday.com's hosted MCP server; OAuth with dynamic client registration. An account admin
 * installs the monday MCP app from the monday marketplace first.
 */
final class MondayConnector extends DynamicOAuthConnector
{
    #[\Override]
    public string $slug { get => 'monday'; }

    #[\Override]
    public string $name { get => 'monday.com'; }

    #[\Override]
    public string $description { get => 'Read and update boards, items, and updates in monday.com.'; }

    #[\Override]
    public ConnectorCategory $category { get => ConnectorCategory::Productivity; }

    #[\Override]
    public string $brandColor { get => 'FF3D57'; }

    #[\Override]
    public int $priority { get => 66; }

    #[\Override]
    public string $mcpUrl { get => 'https://mcp.monday.com/mcp'; }
}
