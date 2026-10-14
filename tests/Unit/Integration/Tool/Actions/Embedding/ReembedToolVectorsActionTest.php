<?php

use Domain\Form\Jobs\EmbedFormDefinitionJob;
use Domain\Form\Models\FormDefinition;
use Domain\Integration\Mcp\Models\ConnectorTool;
use Domain\Integration\Tool\Actions\Embedding\ReembedToolVectorsAction;
use Domain\Integration\Tool\Jobs\EmbedConnectorToolsJob;
use Domain\Knowledge\Embedding\Contracts\EmbeddingsProviderInterface;
use Illuminate\Support\Facades\Bus;

/**
 * Tool routing compares the question with the tools' vectors, so a new
 * embedding provider or model (Settings → AI models) embeds every tool again
 * (started with the knowledge re-index, see EmbeddingReindexTest).
 */
it('clears the tool vectors and queues an embed job for each connector and form', function (): void {
    $vector = app(EmbeddingsProviderInterface::class)->embed('a tool', true);
    $tool = ConnectorTool::factory()->create(['connector_slug' => 'acme', 'embedding' => $vector]);
    $form = FormDefinition::factory()->create();
    FormDefinition::query()->whereKey($form->id)->update(['embedding' => $vector]);

    Bus::fake();

    app(ReembedToolVectorsAction::class)->execute();

    expect(ConnectorTool::query()->whereKey($tool->id)->whereNull('embedding')->exists())->toBeTrue()
        ->and(FormDefinition::query()->whereKey($form->id)->whereNull('embedding')->exists())->toBeTrue();

    Bus::assertDispatched(EmbedConnectorToolsJob::class, fn (EmbedConnectorToolsJob $job): bool => $job->connectorSlug === 'acme');
    Bus::assertDispatched(EmbedFormDefinitionJob::class, fn (EmbedFormDefinitionJob $job): bool => $job->formId === $form->id);
});
