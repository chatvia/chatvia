<?php

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Ingestion\Actions\Website\CompleteWebsiteIngestionAction;
use Domain\Knowledge\Ingestion\Jobs\SummaryIngestionJob;
use Domain\Knowledge\Ingestion\Models\Chunk;
use Domain\Knowledge\Source\Enums\SourceStatus;
use Domain\Knowledge\Source\Services\KnowledgeStoragePath;
use Domain\Knowledge\Source\Web\Events\WebsiteIngestionProgressed;
use Domain\Knowledge\Source\Web\Models\SourceWebsite;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;

/**
 * Finishing a website's training: the website is ingested, its chunks are
 * written as one document in its folder (replaced on a retrain), the URL
 * placeholders come off the chunk count, and the summary is queued.
 */
beforeEach(function (): void {
    Bus::fake();
    Event::fake([WebsiteIngestionProgressed::class]);
    Storage::fake();
});

it('finishes a website: ingested, text file saved, URL placeholders taken off the chunk count', function (): void {
    config(['chatvia.ai.chunker.enhance' => true]);

    $agent = Agent::factory()->create();
    $website = SourceWebsite::factory()->processing()->attachedTo($agent)->create();
    $url = $website->urls()->create(['url' => $website->url.'/a']);
    Chunk::factory()->forSource($website)->referencedBy($url)->create(['chunk' => 'First part of the page.']);
    $website->update(['expected_chunks' => 1 + 1]);

    app(CompleteWebsiteIngestionAction::class)->execute($website, $agent);

    $website->refresh();
    expect($website->status)->toBe(SourceStatus::Completed)
        ->and($website->expected_chunks)->toBe(1)
        ->and($website->filepath)->toStartWith(KnowledgeStoragePath::forSource($website).'/')
        ->and(Storage::get($website->filepath))->toBe('First part of the page.');

    Bus::assertDispatched(SummaryIngestionJob::class);
    Event::assertDispatched(WebsiteIngestionProgressed::class);
});

it('replaces the website document on a retrain', function (): void {
    $agent = Agent::factory()->create();
    $website = SourceWebsite::factory()->processing()->attachedTo($agent)->create();
    $url = $website->urls()->create(['url' => $website->url.'/a']);
    Chunk::factory()->forSource($website)->referencedBy($url)->create(['chunk' => 'First part of the page.']);

    app(CompleteWebsiteIngestionAction::class)->execute($website, $agent);
    $first = $website->fresh()->filepath;

    app(CompleteWebsiteIngestionAction::class)->execute($website, $agent);
    $second = $website->fresh()->filepath;

    expect($second)->not->toBe($first)
        ->and(Storage::allFiles())->toBe([$second]);
});
