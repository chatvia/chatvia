<?php

declare(strict_types=1);

namespace Domain\Integration\Connector\Catalog\Connectors;

use Domain\Integration\Connector\Catalog\PreRegisteredOAuthConnector;
use Domain\Integration\Connector\Enums\ConnectorCategory;

/**
 * Box's hosted MCP server, without dynamic client registration: the admin creates a Box MCP
 * server entry in the Box Admin Console (Integrations) and enters it in Settings → Connectors.
 */
final class BoxConnector extends PreRegisteredOAuthConnector
{
    #[\Override]
    public string $slug { get => 'box'; }

    #[\Override]
    public string $name { get => 'Box'; }

    #[\Override]
    public string $description { get => 'Search and read files, folders, and metadata from your Box account.'; }

    #[\Override]
    public ConnectorCategory $category { get => ConnectorCategory::Productivity; }

    #[\Override]
    public string $brandColor { get => '0061D5'; }

    #[\Override]
    public int $priority { get => 55; }

    #[\Override]
    public string $mcpUrl { get => 'https://mcp.box.com'; }

    #[\Override]
    public string $authorizationEndpoint { get => 'https://account.box.com/api/oauth2/authorize'; }

    #[\Override]
    public string $tokenEndpoint { get => 'https://api.box.com/oauth2/token'; }

    #[\Override]
    public string $scopes { get => 'root_readwrite'; }

    #[\Override]
    public ?string $appConsoleUrl { get => 'https://app.box.com/developers/console'; }
}
