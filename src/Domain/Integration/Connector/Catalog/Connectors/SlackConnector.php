<?php

declare(strict_types=1);

namespace Domain\Integration\Connector\Catalog\Connectors;

use Domain\Integration\Connector\Catalog\PreRegisteredOAuthConnector;
use Domain\Integration\Connector\Enums\ConnectorCategory;

/**
 * Slack's hosted MCP server. Slack has no dynamic client registration, so the admin
 * registers a Slack app and enters it in Settings → Connectors.
 */
final class SlackConnector extends PreRegisteredOAuthConnector
{
    #[\Override]
    public string $slug { get => 'slack'; }

    #[\Override]
    public string $name { get => 'Slack'; }

    #[\Override]
    public string $description { get => 'Search messages, post replies, and read channel history across your Slack workspace.'; }

    #[\Override]
    public ConnectorCategory $category { get => ConnectorCategory::Communication; }

    #[\Override]
    public string $brandColor { get => '4A154B'; }

    #[\Override]
    public int $priority { get => 20; }

    #[\Override]
    public string $mcpUrl { get => 'https://mcp.slack.com/mcp'; }

    #[\Override]
    public string $authorizationEndpoint { get => 'https://slack.com/oauth/v2_user/authorize'; }

    #[\Override]
    public string $tokenEndpoint { get => 'https://slack.com/api/oauth.v2.user.access'; }

    #[\Override]
    public string $scopes { get => 'search:read.public channels:history channels:read groups:history groups:read im:history im:read mpim:history mpim:read chat:write users:read'; }

    #[\Override]
    public ?string $appConsoleUrl { get => 'https://api.slack.com/apps'; }
}
