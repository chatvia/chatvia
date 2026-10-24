<?php

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Ingestion\Actions\Website\StartWebsiteIngestionAction;
use Domain\Knowledge\Ingestion\Jobs\Website\WebsiteUrlTrainJob;
use Domain\Knowledge\Source\Enums\SourceStatus;
use Domain\Knowledge\Source\Web\Models\SourceWebsite;
use Illuminate\Bus\PendingBatch;
use Illuminate\Support\Facades\Bus;

/**
 * Training a crawled website: its pending pages go into one batch on the
 * website ingestion queue, and the website records the batch and its page
 * count.
 */
beforeEach(function (): void {
    Bus::fake();
});

it('trains the pending URLs in one batch and records the batch', function (): void {
    $agent = Agent::factory()->create();
    $website = SourceWebsite::factory()->attachedTo($agent)->create(['url' => 'https://docs.example.test', 'status' => SourceStatus::Crawled]);
    $website->urls()->createMany([['url' => 'https://docs.example.test/a'], ['url' => 'https://docs.example.test/b']]);

    app(StartWebsiteIngestionAction::class)->execute($website, $agent, 'job-2');

    $website->refresh();

    expect($website->status)->toBe(SourceStatus::Processing)
        ->and($website->expected_chunks)->toBe(2)
        ->and($website->internal_ingestion_job_id)->toBe('job-2')
        ->and($website->internal_chunking_batch_id)->not->toBeNull();

    Bus::assertBatched(fn (PendingBatch $batch): bool => $batch->jobs->count() === 2 && $batch->jobs->first() instanceof WebsiteUrlTrainJob);
});

it('runs the website batch on the website ingestion queue', function (): void {
    // A batch pushes its jobs onto its own queue, not the one each job names.
    $agent = Agent::factory()->create();
    $website = SourceWebsite::factory()->attachedTo($agent)->create(['url' => 'https://docs.example.test', 'status' => SourceStatus::Crawled]);
    $website->urls()->create(['url' => 'https://docs.example.test/a']);

    app(StartWebsiteIngestionAction::class)->execute($website, $agent);

    Bus::assertBatched(fn (PendingBatch $batch): bool => $batch->queue() === 'ingestion.website'
        && $batch->jobs->every(fn (WebsiteUrlTrainJob $job): bool => jobQueue($job) === $batch->queue()));
});
