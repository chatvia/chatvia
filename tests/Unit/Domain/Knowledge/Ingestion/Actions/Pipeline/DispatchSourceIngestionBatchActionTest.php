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
use Illuminate\Support\Facades\Queue;

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
    // Enhanced pages get a job each.
    Bus::assertBatched(fn (PendingBatch $pending): bool => $pending->jobs->count() === 3
        && $pending->allowsFailures()
        && str_contains((string) $pending->name, (string) $file->id));
});

it('pushes every chunk job onto the long-running ingestion queue', function (): void {
    // A real batch on a fake queue: a batch pushes its jobs onto its own
    // queue and ignores the one each job names, so this shows where they land.
    Queue::fake();
    config(['chatvia.ai.chunker.enhance' => true]);

    $agent = Agent::factory()->create();
    $file = AgentFileFactory::new()->create(['workspace_id' => $agent->workspace_id]);

    app(DispatchSourceIngestionBatchAction::class)->execute($file, $agent, DocumentChunkIngestionJob::forPages($agent, $file, dsibPages(2)));

    Queue::assertPushedOn('ingestion.chunks', DocumentChunkIngestionJob::class);
    Queue::assertPushed(DocumentChunkIngestionJob::class, 2);
    Queue::assertPushedOn('ingestion.summary', SummaryIngestionJob::class);
});

it('stores plain pages 32 to a job, with text-only neighbours', function (): void {
    config(['chatvia.ai.chunker.enhance' => false]);

    $agent = Agent::factory()->create();
    $file = AgentFileFactory::new()->create(['workspace_id' => $agent->workspace_id]);
    $pages = dsibPages(70)->map(fn (PageData $page): PageData => new PageData($page->pageNumber, $page->content, $page->checksum, imagesBase64: ['image']));

    $jobs = DocumentChunkIngestionJob::forPages($agent, $file, $pages);

    expect($jobs->map(fn (DocumentChunkIngestionJob $job): int => count($job->group->pages))->all())->toBe([32, 32, 6])
        ->and($jobs[1]->group->previousPage->pageNumber)->toBe(32)
        ->and($jobs[1]->group->nextPage->pageNumber)->toBe(65)
        ->and($jobs[1]->group->pages[0]->imagesBase64)->toBe([])
        ->and($jobs[1]->group->previousPage->imagesBase64)->toBe([]);
});

it('gives each enhanced page a job of its own, with its neighbours as context', function (): void {
    config(['chatvia.ai.chunker.enhance' => true]);

    $agent = Agent::factory()->create();
    $file = AgentFileFactory::new()->create(['workspace_id' => $agent->workspace_id, 'mime_type' => 'application/pdf']);
    $pages = dsibPages(3)->map(fn (PageData $page): PageData => new PageData($page->pageNumber, $page->content, $page->checksum, imagesBase64: ['image']));

    $jobs = DocumentChunkIngestionJob::forPages($agent, $file, $pages);

    expect($jobs)->toHaveCount(3)
        ->and($jobs[0]->group->previousPage)->toBeNull()
        ->and($jobs[0]->group->nextPage->pageNumber)->toBe(2)
        ->and($jobs[1]->group->previousPage->pageNumber)->toBe(1)
        ->and($jobs[2]->group->nextPage)->toBeNull()
        // A document page's own images are described; its neighbours' are not.
        ->and($jobs[1]->group->pages[0]->imagesBase64)->toBe(['image'])
        ->and($jobs[1]->group->nextPage->imagesBase64)->toBe([]);
});

it('stores the pages without enhancement once a job has failed for good', function (): void {
    config(['chatvia.ai.chunker.enhance' => true]);

    $agent = Agent::factory()->create();
    $file = AgentFileFactory::new()->create(['workspace_id' => $agent->workspace_id, 'mime_type' => 'application/pdf']);

    $job = DocumentChunkIngestionJob::forPages($agent, $file, dsibPages(1))->first();
    $job->failed(new RuntimeException('model unavailable'));

    expect($file->chunks()->sole())
        ->chunk->toBe('Page 1')
        ->is_enhanced->toBeFalse();
});

it('completes the source straight away when there is nothing to chunk', function (): void {
    Bus::fake();
    Event::fake([FileIngestionProgressed::class]);

    $agent = Agent::factory()->create();
    $file = AgentFileFactory::new()->create(['workspace_id' => $agent->workspace_id]);

    $batch = app(DispatchSourceIngestionBatchAction::class)->execute($file, $agent, collect());

    expect($batch)->toBeNull()
        ->and($file->fresh()->status)->toBe(SourceStatus::Completed)
        ->and($file->fresh()->expected_chunks)->toBe(0);

    Bus::assertNothingBatched();
    Bus::assertNotDispatched(SummaryIngestionJob::class);
    Event::assertDispatched(FileIngestionProgressed::class);
});

it('completes the source and broadcasts the final progress when the batch finishes', function (): void {
    Bus::fake();
    Event::fake([FileIngestionProgressed::class]);

    $agent = Agent::factory()->create();
    $file = AgentFileFactory::new()->create(['workspace_id' => $agent->workspace_id]);

    app(DispatchSourceIngestionBatchAction::class)->execute($file, $agent, DocumentChunkIngestionJob::forPages($agent, $file, dsibPages(1)));

    // Run the batch's `finally` step the way the queue would (it ignores the
    // batch it is given).
    Bus::assertBatched(function (PendingBatch $pending): bool {
        foreach ($pending->finallyCallbacks() as $callback) {
            $callback->getClosure()();
        }

        return true;
    });

    expect($file->fresh()->status)->toBe(SourceStatus::Completed);
    Event::assertDispatched(FileIngestionProgressed::class, fn (FileIngestionProgressed $event): bool => $event->agentExternalId === $agent->external_id);
});

it('queues no summary while enhancement is off, so training makes no model calls', function (): void {
    Bus::fake();
    config(['chatvia.ai.chunker.enhance' => false]);

    $agent = Agent::factory()->create();
    $file = AgentFileFactory::new()->create(['workspace_id' => $agent->workspace_id]);

    app(DispatchSourceIngestionBatchAction::class)->execute($file, $agent, DocumentChunkIngestionJob::forPages($agent, $file, dsibPages(2)));

    Bus::assertNotDispatched(SummaryIngestionJob::class);
});
