<?php

use Database\Factories\AgentFileFactory;
use Domain\Agent\Models\Agent;
use Domain\Knowledge\Ingestion\Jobs\Document\DocumentIngestionJob;
use Domain\Knowledge\Ingestion\Jobs\Media\MediaIngestionJob;
use Domain\Knowledge\Ingestion\Jobs\TextFile\TextFileIngestionJob;
use Domain\Knowledge\Source\Enums\SourceStatus;
use Illuminate\Queue\TimeoutExceededException;

/**
 * The queue reads a job's limit from its `#[Timeout]` attribute (it never calls
 * a `timeout()` method). When the limit is hit, the worker fails the job and
 * runs its failed() handler, which must mark the file as failed so the
 * dashboard does not show it as ingesting forever.
 */
it('marks the source file failed when a long ingestion job times out', function (string $jobClass, int $timeout): void {
    $agent = Agent::factory()->create();
    $file = AgentFileFactory::new()->create(['workspace_id' => $agent->workspace_id, 'status' => 'ingesting']);

    $job = new $jobClass($file, $agent)->withFakeQueueInteractions();

    expect(jobTimeout($job))->toBe($timeout);

    $job->failed(new TimeoutExceededException($jobClass.' has timed out.'));

    expect($file->fresh())
        ->status->toBe(SourceStatus::Failed)
        ->failure_reason->toContain('has timed out');
})->with([
    'document' => [DocumentIngestionJob::class, 1800],
    'media' => [MediaIngestionJob::class, 86000],
    'text file' => [TextFileIngestionJob::class, 600],
]);
