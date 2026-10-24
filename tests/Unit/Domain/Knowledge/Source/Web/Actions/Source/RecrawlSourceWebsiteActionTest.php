<?php

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Source\Enums\SourceStatus;
use Domain\Knowledge\Source\Web\Actions\Source\RecrawlSourceWebsiteAction;
use Domain\Knowledge\Source\Web\Events\WebsiteCrawlStarted;
use Domain\Knowledge\Source\Web\Jobs\Crawler\WebsiteCrawlerJob;
use Domain\Knowledge\Source\Web\Models\SourceWebsite;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;

/**
 * Rediscovering a website's pages queues a new crawl with the options the
 * website was added with, and tells the Web page.
 */
it('rediscovers a website with the path filters it was added with', function (): void {
    Bus::fake();
    Event::fake([WebsiteCrawlStarted::class]);
    $agent = Agent::factory()->create();
    $website = SourceWebsite::factory()->ingested()->attachedTo($agent)->create(['options' => ['include_paths' => 'docs/*']]);

    app(RecrawlSourceWebsiteAction::class)->execute($website, $agent);

    expect($website->fresh()->status)->toBe(SourceStatus::Crawling);
    Bus::assertDispatched(WebsiteCrawlerJob::class, fn (WebsiteCrawlerJob $job): bool => $job->data->include_paths === 'docs/*');
    Event::assertDispatched(WebsiteCrawlStarted::class);
});
