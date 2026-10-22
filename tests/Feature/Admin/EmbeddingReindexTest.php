<?php

use App\Admin\Settings\Actions\ReindexEmbeddingsAction;
use App\Admin\Settings\AiSettings;
use App\Admin\Settings\Jobs\ReindexEmbeddingsJob;
use Domain\Integration\Tool\Jobs\ReembedToolVectorsJob;
use Domain\Knowledge\Ingestion\Models\Chunk;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Testing\Fakes\PendingBatchFake;

/**
 * Vectors of two embedding models cannot be compared, so a change of the
 * embedding service or model in use (from any place that saves the AI
 * settings) embeds everything again: knowledge and tools, in the queue. The
 * job is queued once the save is done (a deferred callback), so these tests
 * run deferred callbacks at once.
 */
beforeEach(function (): void {
    $this->withoutDefer();
});

function reindexEmbeddingSettings(string $provider, string $openAiModel = 'text-embedding-3-small'): AiSettings
{
    $settings = app(AiSettings::class);
    $settings->embedding_provider = $provider;
    $settings->openai_embedding_model = $openAiModel;
    $settings->save();

    return $settings;
}

it('queues a re-index of everything when the embedding model in use changes', function (): void {
    Bus::fake();
    $settings = reindexEmbeddingSettings('openai');
    Bus::fake();

    $settings->openai_embedding_model = 'text-embedding-3-large';
    $settings->save();

    Bus::assertDispatched(ReindexEmbeddingsJob::class);
});

it('re-indexes when the embedding service changes', function (): void {
    Bus::fake();
    $settings = reindexEmbeddingSettings('openai');
    Bus::fake();

    $settings->embedding_provider = 'openrouter';
    $settings->save();

    Bus::assertDispatched(ReindexEmbeddingsJob::class);
});

it('queues the re-index on the long ingestion queue', function (): void {
    Queue::fake();
    $settings = reindexEmbeddingSettings('openai');

    $settings->openai_embedding_model = 'text-embedding-3-large';
    $settings->save();

    Queue::assertPushedOn('ingestion.chunks', ReindexEmbeddingsJob::class);
});

it('leaves the vectors alone when another service\'s model or another setting changes', function (): void {
    Bus::fake();
    $settings = reindexEmbeddingSettings('openai');
    Bus::fake();

    $settings->jina_embedding_model = 'jina-embeddings-v4';
    $settings->history_messages = 12;
    $settings->save();

    Bus::assertNotDispatched(ReindexEmbeddingsJob::class);
});

it('re-embeds the tools and queues the knowledge as one batch', function (): void {
    Bus::fake();
    Chunk::factory()->create(['chunk' => 'Stored text.']);

    expect(app(ReindexEmbeddingsAction::class)->execute())->not->toBeNull();

    Bus::assertDispatched(ReembedToolVectorsJob::class);
    Bus::assertBatched(fn (PendingBatchFake $batch): bool => $batch->name === 'Embedding re-index');
});
