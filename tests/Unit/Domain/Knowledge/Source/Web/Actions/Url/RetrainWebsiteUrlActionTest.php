<?php

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Ingestion\Jobs\Website\WebsiteUrlTrainJob;
use Domain\Knowledge\Source\Enums\SourceStatus;
use Domain\Knowledge\Source\Web\Actions\Url\RetrainWebsiteUrlAction;
use Domain\Knowledge\Source\Web\Models\SourceWebsite;
use Illuminate\Support\Facades\Bus;

/**
 * Retraining one page of a website puts it back to pending and queues its
 * training job.
 */
it('retrains one URL', function (): void {
    Bus::fake();
    $agent = Agent::factory()->create();
    $website = SourceWebsite::factory()->ingested()->attachedTo($agent)->create();
    $url = $website->urls()->create(['url' => $website->url.'/a', 'status' => 'ingested']);

    app(RetrainWebsiteUrlAction::class)->execute($url, $agent);

    expect($url->fresh()->status)->toBe(SourceStatus::Pending);
    Bus::assertDispatched(WebsiteUrlTrainJob::class);
});
