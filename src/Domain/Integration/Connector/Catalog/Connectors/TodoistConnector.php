<?php

declare(strict_types=1);

namespace Domain\Integration\Connector\Catalog\Connectors;

use Domain\Integration\Connector\Catalog\DynamicOAuthConnector;
use Domain\Integration\Connector\Enums\ConnectorCategory;

/**
 * Todoist's hosted MCP server; OAuth with dynamic client registration.
 */
final class TodoistConnector extends DynamicOAuthConnector
{
    #[\Override]
    public string $slug { get => 'todoist'; }

    #[\Override]
    public string $name { get => 'Todoist'; }

    #[\Override]
    public string $description { get => 'Manage tasks, projects, and labels across your Todoist account.'; }

    #[\Override]
    public ConnectorCategory $category { get => ConnectorCategory::Productivity; }

    #[\Override]
    public string $brandColor { get => 'E44332'; }

    #[\Override]
    public int $priority { get => 90; }

    #[\Override]
    public string $mcpUrl { get => 'https://ai.todoist.net/mcp'; }
}
