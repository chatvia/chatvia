<?php

declare(strict_types=1);

use Domain\Integration\Mcp\Actions\Catalog\UpsertConnectorToolsAction;
use Domain\Integration\Mcp\Data\McpToolDefinitionData;
use Domain\Integration\Mcp\Enums\McpToolPermission;
use Domain\Integration\Mcp\Enums\McpToolScope;
use Domain\Integration\Mcp\Models\ConnectorTool;
use Domain\Knowledge\Embedding\Services\EmbeddingStorage;

/**
 * The shared-catalog write used by both the per-workspace sync and the
 * deploy-time builtin sync: catalog rows are stored once per connector, with
 * a scope and default permission from the tool's hints, and keep their
 * embedding until the description changes.
 */

/**
 * A read tool, a write tool and an entry with no name (dropped on the way in).
 *
 * @return list<McpToolDefinitionData>
 */
function uctDefinitions(): array
{
    return McpToolDefinitionData::listFrom([
        ['name' => 'read_thing', 'description' => 'reads', 'annotations' => ['readOnlyHint' => true]],
        ['name' => 'write_thing', 'description' => 'writes'],
        ['description' => 'no name — not a tool'],
    ]);
}

it('stores each tool once, with its scope and default permission', function (): void {
    $tools = app(UpsertConnectorToolsAction::class)->execute('weather', uctDefinitions());
    app(UpsertConnectorToolsAction::class)->execute('weather', uctDefinitions());

    expect($tools)->toHaveCount(2)
        ->and(ConnectorTool::where('connector_slug', 'weather')->count())->toBe(2)
        ->and($tools[0]->scope)->toBe(McpToolScope::Read)
        ->and($tools[0]->default_permission)->toBe(McpToolPermission::Allow)
        ->and($tools[1]->scope)->toBe(McpToolScope::Write)
        ->and($tools[1]->default_permission)->toBe(McpToolPermission::Approval)
        // A missing input schema becomes an empty object schema.
        ->and($tools[1]->input_schema)->toBe(['type' => 'object', 'properties' => []]);
});

it('never gates a first-party builtin, even its write tools', function (): void {
    $tools = app(UpsertConnectorToolsAction::class)->execute('forms', uctDefinitions());

    expect($tools->pluck('default_permission')->unique()->all())->toBe([McpToolPermission::Allow]);
});

it('clears the embedding only when the description changes', function (): void {
    [$read] = app(UpsertConnectorToolsAction::class)->execute('weather', uctDefinitions());
    ConnectorTool::whereKey($read->id)->update(['search_text' => null]);
    $read->forceFill(['embedding' => array_fill(0, app(EmbeddingStorage::class)->dimensions(), 0.1)])->save();

    app(UpsertConnectorToolsAction::class)->execute('weather', uctDefinitions());
    expect($read->fresh()->embedding)->not->toBeNull();

    app(UpsertConnectorToolsAction::class)->execute('weather', McpToolDefinitionData::listFrom([
        ['name' => 'read_thing', 'description' => 'reads, now worded differently', 'annotations' => ['readOnlyHint' => true]],
    ]));
    expect($read->fresh()->embedding)->toBeNull();
});
