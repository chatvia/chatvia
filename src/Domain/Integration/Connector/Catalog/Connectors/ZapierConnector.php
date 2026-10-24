<?php

declare(strict_types=1);

namespace Domain\Integration\Connector\Catalog\Connectors;

use Domain\Integration\Connector\Catalog\DynamicOAuthConnector;
use Domain\Integration\Connector\Catalog\PreRegisteredOAuthConnector;
use Domain\Integration\Connector\Enums\ConnectorCategory;

/**
 * Zapier's hosted MCP server; OAuth with dynamic client registration. If a first connect
 * fails with "client registration unsupported", move it to {@see PreRegisteredOAuthConnector}.
 */
final class ZapierConnector extends DynamicOAuthConnector
{
    #[\Override]
    public string $slug { get => 'zapier'; }

    #[\Override]
    public string $name { get => 'Zapier'; }

    #[\Override]
    public string $description { get => 'Trigger Zapier actions across 9,000+ connected apps.'; }

    #[\Override]
    public ConnectorCategory $category { get => ConnectorCategory::Automation; }

    #[\Override]
    public string $brandColor { get => 'FF4A00'; }

    #[\Override]
    public int $priority { get => 110; }

    #[\Override]
    public string $mcpUrl { get => 'https://mcp.zapier.com/api/mcp/mcp'; }
}
