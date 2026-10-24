<?php

use Database\Factories\AgentFileFactory;
use Domain\Agent\Models\Agent;
use Domain\Knowledge\Ingestion\Actions\Pipeline\MarkSourceFailedAction;
use Domain\Knowledge\Ingestion\Jobs\Text\TextIngestionJob;
use Domain\Knowledge\Source\Enums\SourceStatus;
use Domain\Knowledge\Source\Text\Events\TextIngestionStarted;
use Domain\Knowledge\Source\Text\Models\SourceText;
use Domain\Knowledge\Source\Web\Events\UrlIngestionStarted;
use Domain\Knowledge\Source\Web\Events\WebsiteCrawlStarted;
use Domain\Knowledge\Source\Web\Models\SourceWebsite;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Exceptions;

/**
 * Every ingestion job's failed() hook goes through one action: the source is
 * marked failed with the reason and the page that shows it is told.
 */
beforeEach(function (): void {
    Exceptions::fake();
    Event::fake([TextIngestionStarted::class, WebsiteCrawlStarted::class, UrlIngestionStarted::class]);
});

it('marks a file failed with the reason and reports the exception', function (): void {
    $agent = Agent::factory()->create();
    $file = AgentFileFactory::new()->create(['workspace_id' => $agent->workspace_id, 'status' => 'ingesting']);

    app(MarkSourceFailedAction::class)->execute($file, $agent, new RuntimeException('parser down'));

    expect($file->fresh())
        ->status->toBe(SourceStatus::Failed)
        ->failure_reason->toBe('parser down');

    Exceptions::assertReported(RuntimeException::class);
    Event::assertNothingDispatched();
});

it('tells the Web page when a website or one of its URLs fails', function (): void {
    $agent = Agent::factory()->create();
    $website = SourceWebsite::factory()->attachedTo($agent)->create(['status' => SourceStatus::Crawling]);
    $url = $website->urls()->create(['url' => $website->url.'/a']);

    app(MarkSourceFailedAction::class)->execute($website, $agent, new RuntimeException('map failed'));
    app(MarkSourceFailedAction::class)->execute($url, $agent, new RuntimeException('scrape failed'));

    expect($website->fresh()->status)->toBe(SourceStatus::Failed)
        ->and($url->fresh()->status)->toBe(SourceStatus::Failed);

    Event::assertDispatched(WebsiteCrawlStarted::class, fn (WebsiteCrawlStarted $event): bool => $event->broadcastWith()['status'] === 'failed');
    Event::assertDispatched(UrlIngestionStarted::class, fn (UrlIngestionStarted $event): bool => $event->broadcastWith()['status'] === 'failed');
});

it('marks a text failed when its ingestion job fails, instead of leaving it ingesting', function (): void {
    $agent = Agent::factory()->create();
    $text = SourceText::factory()->processing()->attachedTo($agent)->create();

    new TextIngestionJob($text, $agent)->failed(new RuntimeException('splitter down'));

    expect($text->fresh()->status)->toBe(SourceStatus::Failed);
    Event::assertDispatched(TextIngestionStarted::class, fn (TextIngestionStarted $event): bool => $event->agentExternalId === $agent->external_id);
});
