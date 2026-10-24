<?php

use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Services\Tooling\Routing\SurfaceToolFilter;
use Domain\Integration\Tool\Data\ToolEntry;
use Domain\Integration\Tool\Data\ToolName;

/**
 * A connector the catalog pins to some surfaces offers its tools only on
 * those surfaces' conversations; every tool stays when no connector is pinned
 * or the conversation (its surface) is unknown.
 */

/** A tool entry named the way the registry compiles MCP tools: mcp__{slug}__{tool}. */
function surfaceToolEntry(string $name): ToolEntry
{
    return new ToolEntry(
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
    config()->set('connectors', [
        'charts' => [
            'slug' => 'charts',
            'name' => 'Interactive charts',
            'description' => 'Charts',
            'category' => 'Utilities',
            'icon' => 'charts',
            'brand_color' => '8B5CF6',
            'auth_strategy' => 'none',
            'priority' => 30,
            'surfaces' => ['hosted_chat'],
            'mcp_url' => 'https://example.test/mcp/charts',
        ],
        'weather' => [
            'slug' => 'weather',
            'name' => 'Weather',
            'description' => 'Weather',
            'category' => 'Utilities',
            'icon' => 'weather',
            'brand_color' => '3B82F6',
            'auth_strategy' => 'token',
            'priority' => 10,
            'mcp_url' => 'https://example.test/mcp/weather',
        ],
    ]);
}

function surfaceToolNames(array $entries): array
{
    return array_map(static fn (ToolEntry $e): string => $e->name, $entries);
}

beforeEach(function (): void {
    $this->filter = app(SurfaceToolFilter::class);
    $this->entries = [
        surfaceToolEntry('mcp__charts__render-bar-chart'),
        surfaceToolEntry('mcp__weather__get-weather'),
        surfaceToolEntry('lookup_order'),
    ];
});

it('drops a surface-pinned connector on a surface not in its allow-list', function (ConversationSource $surface): void {
    surfaceCatalogConfig();
    $conversation = Conversation::factory()->create(['source' => $surface]);

    $kept = surfaceToolNames($this->filter->apply($this->entries, $conversation->external_id));

    expect($kept)->toBe(['mcp__weather__get-weather', 'lookup_order']);
})->with([
    'the widget' => [ConversationSource::Widget],
    'a messaging channel' => [ConversationSource::Telegram],
]);

it('keeps a surface-pinned connector on a surface in its allow-list', function (): void {
    surfaceCatalogConfig();
    $conversation = Conversation::factory()->create(['source' => ConversationSource::HostedChat]);

    $kept = surfaceToolNames($this->filter->apply($this->entries, $conversation->external_id));

    expect($kept)->toBe(['mcp__charts__render-bar-chart', 'mcp__weather__get-weather', 'lookup_order']);
});

it('keeps everything when no connector declares a surface restriction', function (): void {
    config()->set('connectors', [
        'weather' => [
            'slug' => 'weather',
            'name' => 'Weather',
            'description' => 'Weather',
            'category' => 'Utilities',
            'icon' => 'weather',
            'brand_color' => '3B82F6',
            'auth_strategy' => 'token',
            'priority' => 10,
            'mcp_url' => 'https://example.test/mcp/weather',
        ],
    ]);
    $conversation = Conversation::factory()->create(['source' => ConversationSource::Widget]);

    $kept = surfaceToolNames($this->filter->apply($this->entries, $conversation->external_id));

    expect($kept)->toBe(surfaceToolNames($this->entries));
});

it('keeps everything when the conversation id is null (surface unknown)', function (): void {
    surfaceCatalogConfig();

    $kept = surfaceToolNames($this->filter->apply($this->entries, null));

    expect($kept)->toBe(surfaceToolNames($this->entries));
});

it('keeps everything when the conversation cannot be found', function (): void {
    surfaceCatalogConfig();

    // A well-formed but absent external id (the streaming layer always passes a
    // real conversation UUID — same assumption StickyToolResolver makes).
    $kept = surfaceToolNames($this->filter->apply($this->entries, '00000000-0000-7000-8000-000000000000'));

    expect($kept)->toBe(surfaceToolNames($this->entries));
});
