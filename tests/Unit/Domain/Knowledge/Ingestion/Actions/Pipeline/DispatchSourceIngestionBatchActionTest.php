<?php

use Database\Factories\AgentFileFactory;
use Domain\Agent\Models\Agent;
use Domain\Knowledge\Ingestion\Actions\Pipeline\DispatchSourceIngestionBatchAction;
use Domain\Knowledge\Ingestion\Data\Ocr\PageData;
use Domain\Knowledge\Ingestion\Jobs\Document\DocumentChunkIngestionJob;
use Domain\Knowledge\Ingestion\Jobs\SummaryIngestionJob;
use Domain\Knowledge\Source\Enums\SourceStatus;
use Domain\Knowledge\Source\File\Events\FileIngestionProgressed;
use Illuminate\Bus\PendingBatch;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

/**
 * The one batch pipeline shared by document, text-file, media and text
 * ingestion: its chunk jobs store groups of pages (one page when enhanced),
 * and its completion step is the authoritative "done" signal — the jobs'
 * progress stays below 100%, so without the final broadcast the sources page
 * would hang on "ingesting".
 */
function dsibPages(int $count): Collection
{
    return collect(range(1, $count))->map(fn (int $n): PageData => new PageData(pageNumber: $n, content: "Page {$n}", checksum: "c{$n}"));
}

it('marks the source as ingesting with its page count, queues the summary and stores the batch id', function (): void {
    Bus::fake();
    config(['chatvia.ai.chunker.enhance' => true]);

    $agent = Agent::factory()->create();
    $file = AgentFileFactory::new()->create(['workspace_id' => $agent->workspace_id]);

    $jobs = DocumentChunkIngestionJob::forPages($agent, $file, dsibPages(3));
    $batch = app(DispatchSourceIngestionBatchAction::class)->execute($file, $agent, $jobs);

    $file->refresh();
    expect($batch)->not->toBeNull()
        ->and($file->status)->toBe(SourceStatus::Processing)
        ->and($file->expected_chunks)->toBe(3)
        ->and($file->internal_chunking_batch_id)->toBe($batch->id);

    Bus::assertDispatched(SummaryIngestionJob::class);
    Bus::assertBatched(fn (PendingBatch $pending): bool => $pending->jobs->count() === 3
        && $pending->allowsFailures()
        && Str::contains((string) $pending->name, (string) $file->id));
});

it('completes the source and broadcasts the final progress when the batch finishes', function (): void {
    Bus::fake();
    Event::fake([FileIngestionProgressed::class]);

    $agent = Agent::factory()->create();
    $file = AgentFileFactory::new()->create(['workspace_id' => $agent->workspace_id]);

    app(DispatchSourceIngestionBatchAction::class)->execute($file, $agent, DocumentChunkIngestionJob::forPages($agent, $file, dsibPages(1)));

    Bus::assertBatched(function (PendingBatch $pending): bool {
        foreach ($pending->finallyCallbacks() as $callback) {
            $callback->getClosure()();
        }

        return true;
    });

    expect($file->fresh()->status)->toBe(SourceStatus::Completed);
    Event::assertDispatched(FileIngestionProgressed::class, fn (FileIngestionProgressed $event): bool => $event->agentExternalId === $agent->external_id);
});
