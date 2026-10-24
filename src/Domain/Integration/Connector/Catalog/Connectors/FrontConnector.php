<?php

declare(strict_types=1);

namespace Domain\Integration\Connector\Catalog\Connectors;

use Domain\Integration\Connector\Catalog\PreRegisteredOAuthConnector;
use Domain\Integration\Connector\Enums\ConnectorCategory;

/**
 * Front's hosted MCP server, without dynamic client registration: the admin creates an OAuth
 * app in Front (Settings → Company → Developers), turns on its MCP server, and enters it in
 * Settings → Connectors.
 */
final class FrontConnector extends PreRegisteredOAuthConnector
{
    #[\Override]
    public string $slug { get => 'front'; }

    #[\Override]
    public string $name { get => 'Front'; }

    #[\Override]
    public string $description { get => 'Read and reply to shared inbox conversations, drafts, tags, and contacts in Front.'; }

    #[\Override]
    public ConnectorCategory $category { get => ConnectorCategory::Support; }

    #[\Override]
    public string $brandColor { get => 'A857F1'; }

    #[\Override]
    public int $priority { get => 36; }

    #[\Override]
    public string $mcpUrl { get => 'https://mcp.frontapp.com/mcp'; }

    #[\Override]
    public string $authorizationEndpoint { get => 'https://app.frontapp.com/oauth/authorize'; }

    #[\Override]
    public string $tokenEndpoint { get => 'https://app.frontapp.com/oauth/token'; }

    #[\Override]
    public string $scopes { get => 'read write send'; }

    #[\Override]
    public ?string $appConsoleUrl { get => 'https://app.frontapp.com/settings/developers'; }
}
