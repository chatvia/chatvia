<?php

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Source\Web\Actions\Refresh\RefreshDueWebsitesAction;
use Domain\Knowledge\Source\Web\Actions\Refresh\SetWebsiteRefreshIntervalAction;
use Domain\Knowledge\Source\Web\Enums\RefreshInterval;
use Domain\Knowledge\Source\Web\Events\WebsiteCrawlStarted;
use Domain\Knowledge\Source\Web\Models\SourceWebsite;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;

/**
 * Setting how often a website is refreshed. Switching it on counts from now,
 * so the website is not refreshed at once; changing between intervals keeps
 * counting from the last refresh.
 */
beforeEach(function (): void {
    Bus::fake();
    Event::fake([WebsiteCrawlStarted::class]);
    $this->freezeSecond();
    $this->website = SourceWebsite::factory()->ingested()->attachedTo(Agent::factory()->create())->create();
});

it('counts from now when switched on again, and from the last refresh between intervals', function (): void {
    $this->website->update(['refresh_interval' => RefreshInterval::Off, 'refreshed_at' => now()->subMonths(3)]);

    app(SetWebsiteRefreshIntervalAction::class)->execute($this->website->fresh(), RefreshInterval::Daily);

    expect($this->website->fresh()->refreshed_at->equalTo(now()))->toBeTrue();

    $tenDaysAgo = now()->subDays(10);
    $this->website->update(['refreshed_at' => $tenDaysAgo]);
    app(SetWebsiteRefreshIntervalAction::class)->execute($this->website->fresh(), RefreshInterval::Weekly);

    expect($this->website->fresh()->refreshed_at->equalTo($tenDaysAgo))->toBeTrue()
        ->and(app(RefreshDueWebsitesAction::class)->execute())->toBe(1);
});
