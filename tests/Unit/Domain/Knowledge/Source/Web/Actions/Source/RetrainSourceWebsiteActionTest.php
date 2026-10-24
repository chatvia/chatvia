<?php

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Ingestion\Jobs\Website\WebsiteIngestionJob;
use Domain\Knowledge\Source\Web\Actions\Source\RetrainSourceWebsiteAction;
use Domain\Knowledge\Source\Web\Jobs\Crawler\WebsiteCrawlerJob;
use Domain\Knowledge\Source\Web\Models\SourceWebsite;
use Illuminate\Support\Facades\Bus;

/**
 * Retraining a website trains the pages it already has again, without a new
 * crawl.
 */
it('retrains a website from the URLs it has', function (): void {
    Bus::fake();
    $agent = Agent::factory()->create();
    $website = SourceWebsite::factory()->ingested()->attachedTo($agent)->create();

    app(RetrainSourceWebsiteAction::class)->execute($website, $agent);

    Bus::assertDispatched(WebsiteIngestionJob::class);
    Bus::assertNotDispatched(WebsiteCrawlerJob::class);
});
