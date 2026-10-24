<?php

declare(strict_types=1);

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
use Illuminate\Support\Facades\Bus;
use Tests\Support\Integration\Tool\ToolVectors;

/**
 * Tool routing compares the question with the tools' vectors, so a new
 * embedding provider or model (Settings → AI models) embeds every tool again
 * (started with the knowledge re-index, see ReindexEmbeddingsActionTest). The
 * keyword (BM25) columns do not depend on the model, so they stay.
 */
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

    expect(ToolVectors::of($connectorTool))->toBe(['dense' => false, 'keyword' => true])
        ->and(ToolVectors::of($mcpTool))->toBe(['dense' => false, 'keyword' => true])
        ->and(ToolVectors::of($function))->toBe(['dense' => false, 'keyword' => true])
        ->and(ToolVectors::of($form))->toBe(['dense' => false, 'keyword' => true]);

    Bus::assertDispatched(EmbedConnectorToolsJob::class, fn (EmbedConnectorToolsJob $job): bool => $job->connectorSlug === 'acme');
    Bus::assertDispatched(EmbedMcpToolsJob::class, fn (EmbedMcpToolsJob $job): bool => $job->mcpServerId === $mcpTool->mcp_server_id);
    Bus::assertDispatched(EmbedFunctionJob::class, fn (EmbedFunctionJob $job): bool => $job->functionId === $function->id);
    Bus::assertDispatched(EmbedFormDefinitionJob::class, fn (EmbedFormDefinitionJob $job): bool => $job->formId === $form->id);
});
