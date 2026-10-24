<?php

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Ingestion\Jobs\Website\WebsiteIngestionJob;
use Domain\Knowledge\Source\Enums\SourceStatus;
use Domain\Knowledge\Source\Web\Actions\Refresh\RefreshDueWebsitesAction;
use Domain\Knowledge\Source\Web\Enums\RefreshInterval;
use Domain\Knowledge\Source\Web\Events\WebsiteCrawlStarted;
use Domain\Knowledge\Source\Web\Jobs\Crawler\WebsiteCrawlerJob;
use Domain\Knowledge\Source\Web\Models\SourceWebsite;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;

/**
 * A website set to refresh is crawled and trained again on its own once its
 * interval has passed: discovery, then training, as one chain, with the
 * options it was added with. Busy, unused and switched-off websites are left
 * alone.
 */
beforeEach(function (): void {
    Bus::fake();
    Event::fake([WebsiteCrawlStarted::class]);
});

it('crawls then trains a website whose interval has passed', function (): void {
    $this->freezeSecond();
    $website = SourceWebsite::factory()->ingested()->attachedTo(Agent::factory()->create())
        ->create(['refresh_interval' => RefreshInterval::Daily, 'refreshed_at' => now()->subDays(2)]);

    expect(app(RefreshDueWebsitesAction::class)->execute())->toBe(1)
        ->and($website->fresh()->status)->toBe(SourceStatus::Crawling)
        ->and($website->fresh()->refreshed_at->equalTo(now()))->toBeTrue();

    Bus::assertChained([WebsiteCrawlerJob::class, WebsiteIngestionJob::class]);
    Event::assertDispatched(WebsiteCrawlStarted::class);
});

it('crawls again with the options the website was added with', function (): void {
    SourceWebsite::factory()->ingested()->attachedTo(Agent::factory()->create())->create([
        'refresh_interval' => RefreshInterval::Daily,
        'options' => ['limit' => 40, 'include_paths' => '/docs/*', 'exclude_paths' => null, 'country' => 'DK', 'languages' => ['da']],
    ]);

    app(RefreshDueWebsitesAction::class)->execute();

    Bus::assertChained([
        fn (WebsiteCrawlerJob $job): bool => $job->data->limit === 40
            && $job->data->include_paths === '/docs/*'
            && $job->data->country === 'DK',
        WebsiteIngestionJob::class,
    ]);
});

it('leaves alone websites that are not due, switched off, busy or unused', function (): void {
    SourceWebsite::factory()->ingested()->attachedTo(Agent::factory()->create())->create(['refresh_interval' => RefreshInterval::Weekly, 'refreshed_at' => now()->subDay()]);
    SourceWebsite::factory()->ingested()->attachedTo(Agent::factory()->create())->create(['refresh_interval' => RefreshInterval::Off]);
    SourceWebsite::factory()->processing()->attachedTo(Agent::factory()->create())->create(['refresh_interval' => RefreshInterval::Daily]);
    SourceWebsite::factory()->ingested()->create(['refresh_interval' => RefreshInterval::Daily]);

    expect(app(RefreshDueWebsitesAction::class)->execute())->toBe(0);

    Bus::assertNothingDispatched();
});
