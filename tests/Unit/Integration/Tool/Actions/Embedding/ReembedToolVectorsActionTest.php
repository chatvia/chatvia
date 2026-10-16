<?php

use Domain\Form\Jobs\EmbedFormDefinitionJob;
use Domain\Form\Models\FormDefinition;
use Domain\Integration\Function\Models\AgentFunction;
use Domain\Integration\Mcp\Models\ConnectorTool;
use Domain\Integration\Mcp\Models\McpTool;
use Domain\Integration\Tool\Actions\Embedding\ReembedToolVectorsAction;
use Domain\Integration\Tool\Jobs\EmbedConnectorToolsJob;
use Domain\Integration\Tool\Jobs\EmbedFunctionJob;
use Domain\Integration\Tool\Jobs\EmbedMcpToolsJob;
use Domain\Knowledge\Embedding\Contracts\EmbeddingsProviderInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Bus;

/**
 * Tool routing compares the question with the tools' vectors, so a new
 * embedding provider or model (Settings → AI models) embeds every tool again
 * (started with the knowledge re-index, see EmbeddingReindexTest). The
 * keyword (BM25) columns do not depend on the model, so they stay.
 */

/** Whether the row has lost its dense vector and kept its keyword vector. */
function reembedClearedDenseOnly(Model $row): bool
{
    return $row->newQuery()->whereKey($row->getKey())->whereNull('embedding')->whereNotNull('search_text')->exists();
}

it('clears the dense vectors, keeps the keyword vectors and queues an embed job for every tool', function (): void {
    $vector = app(EmbeddingsProviderInterface::class)->embed('a tool', true);
    $bm25 = 'a tool';
    $connectorTool = ConnectorTool::factory()->create(['connector_slug' => 'acme']);
    $mcpTool = McpTool::factory()->create();
    // Creating a function embeds it (the observer's job runs on the sync queue).
    $function = AgentFunction::factory()->create();
    $form = FormDefinition::factory()->create();
    foreach ([$connectorTool, $mcpTool, $form] as $row) {
        $row->newQuery()->whereKey($row->getKey())->update(['embedding' => $vector, 'search_text' => $bm25]);
    }

    Bus::fake();

    app(ReembedToolVectorsAction::class)->execute();

    expect(reembedClearedDenseOnly($connectorTool))->toBeTrue()
        ->and(reembedClearedDenseOnly($mcpTool))->toBeTrue()
        ->and(reembedClearedDenseOnly($function))->toBeTrue()
        ->and(reembedClearedDenseOnly($form))->toBeTrue();

    Bus::assertDispatched(EmbedConnectorToolsJob::class, fn (EmbedConnectorToolsJob $job): bool => $job->connectorSlug === 'acme');
    Bus::assertDispatched(EmbedMcpToolsJob::class, fn (EmbedMcpToolsJob $job): bool => $job->mcpServerId === $mcpTool->mcp_server_id);
    Bus::assertDispatched(EmbedFunctionJob::class, fn (EmbedFunctionJob $job): bool => $job->functionId === $function->id);
    Bus::assertDispatched(EmbedFormDefinitionJob::class, fn (EmbedFormDefinitionJob $job): bool => $job->formId === $form->id);
});
