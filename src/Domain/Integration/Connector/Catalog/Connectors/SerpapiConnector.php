<?php

declare(strict_types=1);

namespace Domain\Integration\Connector\Catalog\Connectors;

use Domain\Integration\Connector\Catalog\TokenConnector;
use Domain\Integration\Connector\Enums\ConnectorCategory;

/**
 * SerpApi's hosted MCP server, keyed by the workspace's SerpApi API key as a bearer token. The
 * header form (not SerpApi's `/KEY/mcp` path) keeps the key out of the stored URL, so every
 * workspace shares the canonical tool catalog.
 */
final class SerpapiConnector extends TokenConnector
{
    #[\Override]
    public string $slug { get => 'serpapi'; }

    #[\Override]
    public string $name { get => 'SerpApi'; }

    #[\Override]
    public string $description { get => 'Search Google, Bing, YouTube, and other engines for live results — news, prices, locations — powered by SerpApi.'; }

    #[\Override]
    public ConnectorCategory $category { get => ConnectorCategory::Utilities; }

    #[\Override]
    public string $brandColor { get => '377FEA'; }

    #[\Override]
    public int $priority { get => 15; }

    #[\Override]
    public string $mcpUrl { get => 'https://mcp.serpapi.com/mcp'; }

    #[\Override]
    public ?string $activityLabel { get => 'Searching the web…'; }

    #[\Override]
    public string $tokenLabel { get => 'SerpApi API key'; }

    #[\Override]
    public string $tokenHelpUrl { get => 'https://serpapi.com/manage-api-key'; }
}
