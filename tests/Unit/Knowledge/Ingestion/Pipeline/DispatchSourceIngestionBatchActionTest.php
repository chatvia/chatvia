<?php

use Database\Factories\AgentFileFactory;
use Domain\Agent\Models\Agent;
use Domain\Knowledge\Ingestion\Actions\Pipeline\CompleteSourceIngestionAction;
use Domain\Knowledge\Ingestion\Actions\Pipeline\DispatchSourceIngestionBatchAction;
use Domain\Knowledge\Ingestion\Data\Ocr\PageData;
use Domain\Knowledge\Ingestion\Jobs\Document\DocumentChunkIngestionJob;
use Domain\Knowledge\Ingestion\Jobs\SummaryIngestionJob;
use Domain\Knowledge\Source\Enums\SourceStatus;
use Domain\Knowledge\Source\File\Events\FileIngestionProgressed;
use Domain\Knowledge\Source\Text\Events\TextIngestionStarted;
use Domain\Knowledge\Source\Text\Models\SourceText;
use Illuminate\Bus\PendingBatch;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;

/**
 * The one batch pipeline shared by document, text-file, media and text
 * ingestion. Its completion step is the authoritative "done" signal: per-chunk
 * progress can stay below 100%, so without the final broadcast the sources
 * page would hang on "ingesting".
 */
function dsibPages(int $count): Collection
{
    return collect(range(1, $count))->map(fn (int $n): PageData => new PageData(pageNumber: $n, content: "Page {$n}", checksum: "c{$n}"));
}

it('marks the source as ingesting, queues the summary and stores the batch id', function (): void {
    Bus::fake();

    $agent = Agent::factory()->create();
    $file = AgentFileFactory::new()->create(['user_id' => $agent->user_id]);

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
        && str_contains((string) $pending->name, (string) $file->id));
});

it('gives each page job its neighbours as context', function (): void {
    $agent = Agent::factory()->create();
    $file = AgentFileFactory::new()->create(['user_id' => $agent->user_id]);

    $jobs = DocumentChunkIngestionJob::forPages($agent, $file, dsibPages(3));

    expect($jobs[0]->previousPage)->toBeNull()
        ->and($jobs[0]->nextPage->pageNumber)->toBe(2)
        ->and($jobs[1]->previousPage->pageNumber)->toBe(1)
        ->and($jobs[2]->nextPage)->toBeNull();
});

it('completes the source straight away when there is nothing to chunk', function (): void {
    Bus::fake();
    Event::fake([FileIngestionProgressed::class]);

    $agent = Agent::factory()->create();
    $file = AgentFileFactory::new()->create(['user_id' => $agent->user_id]);

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
    $file = AgentFileFactory::new()->create(['user_id' => $agent->user_id]);

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

it('broadcasts a text source completion on the text channel', function (): void {
    Event::fake([TextIngestionStarted::class]);

    $agent = Agent::factory()->create();
    $text = SourceText::query()->create([
        'user_id' => $agent->user_id,
        'filepath' => 'users/x/agents/y/train/text/a.txt',
        'excerpt' => 'excerpt',
        'length' => 1,
        'checksum' => md5(uniqid()),
        'status' => SourceStatus::Processing,
    ]);

    app(CompleteSourceIngestionAction::class)->execute($text, $agent->external_id);

    expect($text->fresh()->status)->toBe(SourceStatus::Completed);
    Event::assertDispatched(TextIngestionStarted::class, fn (TextIngestionStarted $event): bool => $event->broadcastWith()['status'] === 'ingested');
});
