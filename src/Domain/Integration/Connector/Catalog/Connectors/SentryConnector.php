<?php

declare(strict_types=1);

namespace Domain\Integration\Connector\Catalog\Connectors;

use Domain\Integration\Connector\Catalog\DynamicOAuthConnector;
use Domain\Integration\Connector\Enums\ConnectorCategory;

/**
 * Sentry's hosted MCP server; OAuth with dynamic client registration.
 */
final class SentryConnector extends DynamicOAuthConnector
{
    #[\Override]
    public string $slug { get => 'sentry'; }

    #[\Override]
    public string $name { get => 'Sentry'; }

    #[\Override]
    public string $description { get => 'Read issues, errors, and releases from your Sentry projects.'; }

    #[\Override]
    public ConnectorCategory $category { get => ConnectorCategory::DeveloperTools; }

    #[\Override]
    public string $brandColor { get => '362D59'; }

    #[\Override]
    public int $priority { get => 75; }

    #[\Override]
    public string $mcpUrl { get => 'https://mcp.sentry.dev/mcp'; }
}
