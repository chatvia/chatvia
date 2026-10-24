<?php

declare(strict_types=1);

namespace Domain\Integration\Connector\Catalog\Connectors;

use Domain\Integration\Connector\Catalog\PreRegisteredOAuthConnector;
use Domain\Integration\Connector\Enums\ConnectorCategory;

/**
 * Asana's V2 MCP server, without dynamic client registration: the admin registers an OAuth
 * app (Asana allowlists its redirect URLs) and enters it in Settings → Connectors.
 */
final class AsanaConnector extends PreRegisteredOAuthConnector
{
    #[\Override]
    public string $slug { get => 'asana'; }

    #[\Override]
    public string $name { get => 'Asana'; }

    #[\Override]
    public string $description { get => 'Read and update tasks, projects, and portfolios across your Asana workspace.'; }

    #[\Override]
    public ConnectorCategory $category { get => ConnectorCategory::Productivity; }

    #[\Override]
    public string $brandColor { get => 'F06A6A'; }

    #[\Override]
    public int $priority { get => 65; }

    #[\Override]
    public string $mcpUrl { get => 'https://mcp.asana.com/v2/mcp'; }

    #[\Override]
    public string $authorizationEndpoint { get => 'https://app.asana.com/-/oauth_authorize'; }

    #[\Override]
    public string $tokenEndpoint { get => 'https://app.asana.com/-/oauth_token'; }

    #[\Override]
    public string $scopes { get => 'default'; }

    #[\Override]
    public ?string $appConsoleUrl { get => 'https://app.asana.com/0/my-apps'; }
}
