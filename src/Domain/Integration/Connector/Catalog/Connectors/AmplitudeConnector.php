<?php

declare(strict_types=1);

namespace Domain\Integration\Connector\Catalog\Connectors;

use Domain\Integration\Connector\Catalog\DynamicOAuthConnector;
use Domain\Integration\Connector\Enums\ConnectorCategory;

/**
 * Amplitude's hosted MCP server; OAuth with dynamic client registration.
 */
final class AmplitudeConnector extends DynamicOAuthConnector
{
    #[\Override]
    public string $slug { get => 'amplitude'; }

    #[\Override]
    public string $name { get => 'Amplitude'; }

    #[\Override]
    public string $description { get => 'Query product analytics — events, charts, and cohorts — from your Amplitude project.'; }

    #[\Override]
    public ConnectorCategory $category { get => ConnectorCategory::Analytics; }

    #[\Override]
    public string $brandColor { get => '1E61F0'; }

    #[\Override]
    public int $priority { get => 95; }

    #[\Override]
    public string $mcpUrl { get => 'https://mcp.amplitude.com/mcp'; }
}
