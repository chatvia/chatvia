<?php

declare(strict_types=1);

namespace Domain\Integration\Connector\Catalog\Connectors;

use Domain\Integration\Connector\Catalog\DynamicOAuthConnector;
use Domain\Integration\Connector\Enums\ConnectorCategory;

/**
 * Atlassian's `authv2` MCP endpoint (Jira, Confluence). Its OAuth server advertises dynamic
 * client registration, so it must not become a pre-registered connector: the OAuth start's
 * pre-flight guard would then force the pre-registered path.
 */
final class AtlassianConnector extends DynamicOAuthConnector
{
    #[\Override]
    public string $slug { get => 'atlassian'; }

    #[\Override]
    public string $name { get => 'Atlassian'; }

    #[\Override]
    public string $description { get => 'Jira issues, Confluence pages, and workflows from your Atlassian sites.'; }

    #[\Override]
    public ConnectorCategory $category { get => ConnectorCategory::Productivity; }

    #[\Override]
    public string $brandColor { get => '0052CC'; }

    #[\Override]
    public int $priority { get => 60; }

    #[\Override]
    public string $mcpUrl { get => 'https://mcp.atlassian.com/v1/mcp/authv2'; }
}
