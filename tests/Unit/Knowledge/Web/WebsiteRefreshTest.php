<?php

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Ingestion\Jobs\Website\WebsiteIngestionJob;
use Domain\Knowledge\Source\Enums\SourceStatus;
use Domain\Knowledge\Source\Web\Actions\Refresh\RefreshDueWebsitesAction;
use Domain\Knowledge\Source\Web\Actions\Refresh\SetWebsiteRefreshIntervalAction;
use Domain\Knowledge\Source\Web\Enums\RefreshInterval;
use Domain\Knowledge\Source\Web\Events\WebsiteCrawlStarted;
use Domain\Knowledge\Source\Web\Jobs\Crawler\WebsiteCrawlerJob;
use Domain\Knowledge\Source\Web\Models\SourceWebsite;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;

/**
 * A website set to refresh is crawled and trained again on its own once its
 * interval has passed: discovery, then training, as one chain. Busy, unused
 * and switched-off websites are left alone.
 */
function refreshWebsite(array $attributes = [], bool $attached = true): SourceWebsite
{
    $agent = Agent::factory()->create();
    $website = SourceWebsite::query()->create([
        'workspace_id' => $agent->workspace_id,
        'url' => 'https://docs.example.test/'.uniqid(),
        'status' => SourceStatus::Completed,
        ...$attributes,
    ]);

    if ($attached) {
        $agent->websites()->attach($website->id);
    }

    return $website;
}

beforeEach(function (): void {
    Bus::fake();
    Event::fake([WebsiteCrawlStarted::class]);
});

it('knows when each interval is due', function (RefreshInterval $interval, ?string $refreshedAt, bool $due): void {
    $now = now();

    expect($interval->isDue($refreshedAt === null ? null : $now->copy()->sub($refreshedAt), $now))->toBe($due);
})->with([
    'off is never due' => [RefreshInterval::Off, null, false],
    'never refreshed' => [RefreshInterval::Weekly, null, true],
    'daily, a day ago' => [RefreshInterval::Daily, '1 day', true],
    'daily, an hour ago' => [RefreshInterval::Daily, '1 hour', false],
    'weekly, six days ago' => [RefreshInterval::Weekly, '6 days', false],
    'monthly, a month ago' => [RefreshInterval::Monthly, '1 month', true],
]);

it('crawls then trains a website whose interval has passed', function (): void {
    $website = refreshWebsite(['refresh_interval' => RefreshInterval::Daily, 'refreshed_at' => now()->subDays(2)]);

    expect(app(RefreshDueWebsitesAction::class)->execute())->toBe(1)
        ->and($website->fresh()->status)->toBe(SourceStatus::Crawling)
        ->and($website->fresh()->refreshed_at->isToday())->toBeTrue();

    Bus::assertChained([WebsiteCrawlerJob::class, WebsiteIngestionJob::class]);
    Event::assertDispatched(WebsiteCrawlStarted::class);
});

it('crawls again with the options the website was added with', function (): void {
    refreshWebsite([
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
    refreshWebsite(['refresh_interval' => RefreshInterval::Weekly, 'refreshed_at' => now()->subDay()]);
    refreshWebsite(['refresh_interval' => RefreshInterval::Off]);
    refreshWebsite(['refresh_interval' => RefreshInterval::Daily, 'status' => SourceStatus::Processing]);
    refreshWebsite(['refresh_interval' => RefreshInterval::Daily], attached: false);

    expect(app(RefreshDueWebsitesAction::class)->execute())->toBe(0);

    Bus::assertNothingDispatched();
});

it('sets the interval, counting from now', function (): void {
    $website = refreshWebsite();

    app(SetWebsiteRefreshIntervalAction::class)->execute($website, RefreshInterval::Weekly);

    expect($website->fresh()->refresh_interval)->toBe(RefreshInterval::Weekly)
        ->and($website->fresh()->refreshed_at)->not->toBeNull()
        ->and(app(RefreshDueWebsitesAction::class)->execute())->toBe(0);

    app(SetWebsiteRefreshIntervalAction::class)->execute($website, RefreshInterval::Off);

    expect($website->fresh()->refresh_interval)->toBe(RefreshInterval::Off);
});

it('counts from now when switched on again, and from the last refresh between intervals', function (): void {
    $website = refreshWebsite(['refresh_interval' => RefreshInterval::Off, 'refreshed_at' => now()->subMonths(3)]);

    app(SetWebsiteRefreshIntervalAction::class)->execute($website, RefreshInterval::Daily);

    expect($website->fresh()->refreshed_at->isToday())->toBeTrue();

    $website->update(['refreshed_at' => now()->subDays(10)]);
    app(SetWebsiteRefreshIntervalAction::class)->execute($website->fresh(), RefreshInterval::Weekly);

    expect($website->fresh()->refreshed_at->isToday())->toBeFalse()
        ->and(app(RefreshDueWebsitesAction::class)->execute())->toBe(1);
});
