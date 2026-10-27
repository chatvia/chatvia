<?php

declare(strict_types=1);

namespace Domain\Integration\Connector\Catalog\Connectors;

use Domain\Integration\Connector\Catalog\Concerns\HostsOnChatvia;
use Domain\Integration\Connector\Catalog\TokenConnector;
use Domain\Integration\Connector\Enums\ConnectorCategory;

/**
 * Live web search with Perplexity Sonar. This app hosts the MCP server but never pays for
 * the search: the workspace's own Perplexity API key is sent as a bearer token and read back
 * by `PerplexityTool`.
 */
final class PerplexityConnector extends TokenConnector
{
    use HostsOnChatvia;

    #[\Override]
    public string $slug { get => 'perplexity'; }

    #[\Override]
    public string $name { get => 'Perplexity'; }

    #[\Override]
    public string $description { get => 'Search the live web with Perplexity Sonar — news, prices, recent events — with cited sources.'; }

    #[\Override]
    public ConnectorCategory $category { get => ConnectorCategory::Utilities; }

    #[\Override]
    public string $brandColor { get => '22B8CD'; }

    #[\Override]
    public int $priority { get => 5; }

    #[\Override]
    public ?string $activityLabel { get => 'Searching the web…'; }

    #[\Override]
    public string $tokenLabel { get => 'Perplexity API key'; }

    #[\Override]
    public string $tokenHelpUrl { get => 'https://www.perplexity.ai/account/api/keys'; }
}
