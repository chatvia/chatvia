<?php

declare(strict_types=1);

use Domain\Integration\Mcp\Models\ConnectorTool;
use Domain\Integration\Tool\Actions\Embedding\EmbedConnectorToolsAction;
use Domain\Knowledge\Embedding\Contracts\EmbeddingsProvider;
use Tests\Support\Integration\Tool\ToolVectors;
use Tests\Support\Knowledge\Embedding\FakeEmbeddingsProvider;

/**
 * Embedding a connector's shared catalog rows: only that connector's rows
 * that have no vectors yet, so later connects pay nothing.
 */

/** A fresh embeddings fake, bound for the action; it records what it embeds. */
function ectRecordingEmbedder(): FakeEmbeddingsProvider
{
    $embedder = new FakeEmbeddingsProvider;
    app()->instance(EmbeddingsProvider::class, $embedder);

    return $embedder;
}

it('embeds the shared catalog rows of one connector only', function (): void {
    $first = ConnectorTool::factory()->create(['connector_slug' => 'acme-crm', 'name' => 'lookup']);
    $second = ConnectorTool::factory()->create(['connector_slug' => 'acme-crm', 'name' => 'update']);
    $other = ConnectorTool::factory()->create(['connector_slug' => 'other-crm', 'name' => 'search']);

    app(EmbedConnectorToolsAction::class)->execute('acme-crm');

    expect(ToolVectors::embedded($first))->toBeTrue()
        ->and(ToolVectors::embedded($second))->toBeTrue()
        ->and(ToolVectors::embedded($other))->toBeFalse();
});

it('skips rows that are already embedded, so later connects pay nothing', function (): void {
    ConnectorTool::factory()->create(['connector_slug' => 'acme-crm', 'name' => 'lookup']);
    app(EmbedConnectorToolsAction::class)->execute('acme-crm');

    $embedder = ectRecordingEmbedder();

    app(EmbedConnectorToolsAction::class)->execute('acme-crm');

    expect($embedder->texts)->toBe([]);
});
