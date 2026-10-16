<?php

use Domain\Integration\Mcp\Models\ConnectorTool;
use Domain\Integration\Tool\Actions\Embedding\EmbedConnectorToolsAction;
use Domain\Knowledge\Embedding\Contracts\EmbeddingsProviderInterface;
use Tests\Support\FakeEmbeddingsProvider;

function connectorToolIsEmbedded(ConnectorTool $tool): bool
{
    $row = ConnectorTool::query()
        ->whereKey($tool->id)
        ->selectRaw('embedding IS NOT NULL AND search_text IS NOT NULL AS embedded')
        ->first();

    return (bool) $row->embedded;
}

/** A fresh embeddings fake, bound for the action; it records what it embeds. */
function ectRecordingEmbedder(): FakeEmbeddingsProvider
{
    $embedder = new FakeEmbeddingsProvider;
    app()->instance(EmbeddingsProviderInterface::class, $embedder);

    return $embedder;
}

it('embeds the shared catalog rows of one connector only', function (): void {
    $first = ConnectorTool::factory()->create(['connector_slug' => 'acme-crm', 'name' => 'lookup']);
    $second = ConnectorTool::factory()->create(['connector_slug' => 'acme-crm', 'name' => 'update']);
    $other = ConnectorTool::factory()->create(['connector_slug' => 'other-crm', 'name' => 'search']);

    app(EmbedConnectorToolsAction::class)->execute('acme-crm');

    expect(connectorToolIsEmbedded($first))->toBeTrue()
        ->and(connectorToolIsEmbedded($second))->toBeTrue()
        ->and(connectorToolIsEmbedded($other))->toBeFalse();
});

it('skips rows that are already embedded, so later connects pay nothing', function (): void {
    ConnectorTool::factory()->create(['connector_slug' => 'acme-crm', 'name' => 'lookup']);
    app(EmbedConnectorToolsAction::class)->execute('acme-crm');

    $embedder = ectRecordingEmbedder();

    app(EmbedConnectorToolsAction::class)->execute('acme-crm');

    expect($embedder->texts)->toBe([]);
});

it('embeds a builtin connector name and description from the MCP registry', function (): void {
    config()->set('mcp.builtin', [
        ['slug' => 'acme-crm', 'name' => 'Acme CRM', 'description' => 'Customer records'],
    ]);
    ConnectorTool::factory()->create(['connector_slug' => 'acme-crm', 'name' => 'lookup', 'description' => 'Find a customer']);

    $embedder = ectRecordingEmbedder();

    app(EmbedConnectorToolsAction::class)->execute('acme-crm');

    expect($embedder->texts)->toBe(["Acme CRM\nCustomer records\n\nlookup\n\nFind a customer"]);
});
