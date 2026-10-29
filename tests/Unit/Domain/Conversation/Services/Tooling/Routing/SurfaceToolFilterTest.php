<?php

use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Services\Tooling\Routing\SurfaceToolFilter;
use Domain\Integration\Connector\Catalog\Connectors\ChartsConnector;
use Domain\Integration\Connector\Catalog\Connectors\WeatherConnector;
use Domain\Integration\Tool\Data\ToolEntryData;
use Domain\Integration\Tool\ValueObjects\ToolName;

/**
 * A connector the catalog pins to some surfaces offers its tools only on
 * those surfaces' conversations; every tool stays when no connector is pinned
 * or the conversation (its surface) is unknown.
 */
/** A tool entry named the way the registry compiles MCP tools: mcp__{slug}__{tool}. */
function surfaceToolEntry(string $name): ToolEntryData
{
    return new ToolEntryData(
        kind: ToolName::parse($name)->kind,
        sourceId: 'x:'.$name,
        name: $name,
        description: '',
        parameters: [],
    );
}

/** Minimal connectors catalog: charts pinned to Hosted Chat, weather unrestricted. */
function surfaceCatalogConfig(): void
{
    config()->set('connectors.catalog', [ChartsConnector::class, WeatherConnector::class]);
}

function surfaceToolNames(array $entries): array
{
    return array_map(static fn (ToolEntryData $e): string => $e->name, $entries);
}

beforeEach(function (): void {
    $this->filter = app(SurfaceToolFilter::class);
    $this->entries = [
        surfaceToolEntry('mcp__charts__render-bar-chart'),
        surfaceToolEntry('mcp__weather__get-weather'),
        surfaceToolEntry('lookup_order'),
    ];
});

it('drops a surface-pinned connector on a surface not in its allow-list and keeps it on one that is', function (): void {
    surfaceCatalogConfig();
    $widget = Conversation::factory()->create(['source' => ConversationSource::Widget]);
    $hosted = Conversation::factory()->create(['source' => ConversationSource::HostedChat]);

    expect(surfaceToolNames($this->filter->apply($this->entries, $widget->external_id)))->toBe(['mcp__weather__get-weather', 'lookup_order'])
        ->and(surfaceToolNames($this->filter->apply($this->entries, $hosted->external_id)))->toBe(surfaceToolNames($this->entries));
});

it('keeps everything when the surface is unknown or no connector is pinned', function (): void {
    surfaceCatalogConfig();
    $unknown = [
        surfaceToolNames($this->filter->apply($this->entries, null)),
        surfaceToolNames($this->filter->apply($this->entries, '00000000-0000-7000-8000-000000000000')),
    ];
    config()->set('connectors.catalog', [WeatherConnector::class]);
    $widget = Conversation::factory()->create(['source' => ConversationSource::Widget]);

    expect($unknown)->toBe([surfaceToolNames($this->entries), surfaceToolNames($this->entries)])
        ->and(surfaceToolNames($this->filter->apply($this->entries, $widget->external_id)))->toBe(surfaceToolNames($this->entries));
});
