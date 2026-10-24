<?php

declare(strict_types=1);

namespace Domain\Integration\Connector\Catalog\Connectors;

use Domain\Integration\Connector\Catalog\TokenConnector;
use Domain\Integration\Connector\Enums\ConnectorCategory;

/**
 * Zendesk hosts no MCP server of its own, so each workspace runs one (self-hosted, community
 * or partner) and gives its address together with a Zendesk API token at connect time.
 */
final class ZendeskConnector extends TokenConnector
{
    #[\Override]
    public string $slug { get => 'zendesk'; }

    #[\Override]
    public string $name { get => 'Zendesk'; }

    #[\Override]
    public string $description { get => 'Read tickets, users, and Help Center articles from your Zendesk Support account.'; }

    #[\Override]
    public ConnectorCategory $category { get => ConnectorCategory::Communication; }

    #[\Override]
    public string $brandColor { get => '03363D'; }

    #[\Override]
    public int $priority { get => 35; }

    #[\Override]
    public string $mcpUrl { get => ''; }

    #[\Override]
    public string $tokenLabel { get => 'Zendesk API token'; }

    #[\Override]
    public string $tokenHelpUrl { get => 'https://support.zendesk.com/hc/en-us/articles/4408889192858-Managing-access-to-the-Zendesk-API'; }

    #[\Override]
    public bool $requiresUrl { get => true; }
}
