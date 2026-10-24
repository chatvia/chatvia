<?php

use App\Admin\Settings\Actions\ReindexEmbeddingsAction;
use Domain\Integration\Tool\Jobs\ReembedToolVectorsJob;
use Domain\Knowledge\Ingestion\Models\Chunk;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Testing\Fakes\PendingBatchFake;

/**
 * ReindexEmbeddingsAction embeds everything again with the embedding model
 * now in use: the tool vectors at once, and the knowledge as one batch the
 * AI models page follows.
 */
it('re-embeds the tools and queues the knowledge as one batch', function (): void {
    Bus::fake();
    Chunk::factory()->create(['chunk' => 'Stored text.']);

    expect(app(ReindexEmbeddingsAction::class)->execute())->not->toBeNull();

    Bus::assertDispatched(ReembedToolVectorsJob::class);
    Bus::assertBatched(fn (PendingBatchFake $batch): bool => $batch->name === 'Embedding re-index');
});
