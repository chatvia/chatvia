<?php

declare(strict_types=1);

namespace Domain\Integration\Connector\Catalog\Connectors;

use Domain\Integration\Connector\Catalog\DynamicOAuthConnector;
use Domain\Integration\Connector\Enums\ConnectorCategory;

/**
 * Gorgias' hosted MCP server; OAuth with dynamic client registration. The person enters their
 * Gorgias subdomain on the consent screen.
 */
final class GorgiasConnector extends DynamicOAuthConnector
{
    #[\Override]
    public string $slug { get => 'gorgias'; }

    #[\Override]
    public string $name { get => 'Gorgias'; }

    #[\Override]
    public string $description { get => 'Read and answer tickets, add internal notes, and use macros in your Gorgias helpdesk.'; }

    #[\Override]
    public ConnectorCategory $category { get => ConnectorCategory::Support; }

    #[\Override]
    public string $brandColor { get => '161616'; }

    #[\Override]
    public int $priority { get => 33; }

    #[\Override]
    public string $mcpUrl { get => 'https://mcp.gorgias.com/mcp'; }
}
