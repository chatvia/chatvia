<?php

declare(strict_types=1);

namespace Domain\Integration\Connector\Catalog\Connectors;

use Domain\Integration\Connector\Catalog\DynamicOAuthConnector;
use Domain\Integration\Connector\Enums\ConnectorCategory;

/**
 * Intercom's hosted MCP server (US region); OAuth with dynamic client registration. It cannot
 * send replies a customer sees. EU workspaces use `mcp.eu.intercom.com`, which this preset
 * does not cover yet.
 */
final class IntercomConnector extends DynamicOAuthConnector
{
    #[\Override]
    public string $slug { get => 'intercom'; }

    #[\Override]
    public string $name { get => 'Intercom'; }

    #[\Override]
    public string $description { get => 'Search conversations, contacts, and help articles in Intercom, and add internal notes.'; }

    #[\Override]
    public ConnectorCategory $category { get => ConnectorCategory::Support; }

    #[\Override]
    public string $brandColor { get => '1F8DED'; }

    #[\Override]
    public int $priority { get => 32; }

    #[\Override]
    public string $mcpUrl { get => 'https://mcp.intercom.com/mcp'; }
}
