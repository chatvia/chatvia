<?php

declare(strict_types=1);

namespace Domain\Integration\Connector\Catalog\Connectors;

use Domain\Integration\Connector\Catalog\CapabilityConnector;
use Domain\Integration\Connector\Enums\ConnectorCategory;

/**
 * Interactive charts drawn inline in the chat. Only Hosted Chat can lay out an ECharts canvas,
 * so the router withholds the tools on every other surface, and a voice call never offers them.
 * The global row `chatvia:mcp:sync-builtin` keeps (config/mcp.php) is not ambient for agents: it
 * backs the owner's "Ask your data" chat and seeds the shared tool catalog.
 */
final class ChartsConnector extends CapabilityConnector
{
    #[\Override]
    public string $slug { get => 'charts'; }

    #[\Override]
    public string $name { get => 'Interactive charts'; }

    #[\Override]
    public string $description { get => 'Render interactive charts inline in the chat — bar, line, pie, scatter, radar, gauge, heatmap, and more.'; }

    #[\Override]
    public ConnectorCategory $category { get => ConnectorCategory::Utilities; }

    #[\Override]
    public string $brandColor { get => '8B5CF6'; }

    #[\Override]
    public int $priority { get => 30; }

    #[\Override]
    public string $logoUrl { get => '/images/logos/connectors/interactive-charts.svg'; }

    #[\Override]
    public ?string $activityLabel { get => 'Building the chart…'; }

    /** @var list<string>|null */
    #[\Override]
    public ?array $surfaces { get => ['hosted_chat']; }

    #[\Override]
    public bool $voice { get => false; }
}
