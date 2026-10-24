<?php

declare(strict_types=1);

namespace Domain\Integration\Connector\Catalog\Connectors;

use Domain\Integration\Connector\Catalog\DynamicOAuthConnector;
use Domain\Integration\Connector\Enums\ConnectorCategory;

/**
 * Fireflies' hosted MCP server; OAuth with dynamic client registration, so people sign in
 * with Fireflies instead of pasting an API key.
 */
final class FirefliesConnector extends DynamicOAuthConnector
{
    #[\Override]
    public string $slug { get => 'fireflies'; }

    #[\Override]
    public string $name { get => 'Fireflies'; }

    #[\Override]
    public string $description { get => 'Search meeting transcripts, summaries, and action items from Fireflies.ai.'; }

    #[\Override]
    public ConnectorCategory $category { get => ConnectorCategory::Productivity; }

    #[\Override]
    public string $brandColor { get => 'FF6B00'; }

    #[\Override]
    public int $priority { get => 85; }

    #[\Override]
    public string $mcpUrl { get => 'https://api.fireflies.ai/mcp'; }
}
