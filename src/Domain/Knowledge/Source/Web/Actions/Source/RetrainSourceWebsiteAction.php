<?php

namespace Domain\Knowledge\Source\Web\Actions\Source;

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Ingestion\Jobs\Website\WebsiteIngestionJob;
use Domain\Knowledge\Source\Web\Models\SourceWebsite;

/**
 * Trains a website again from the URLs it already has: every pending URL is
 * scraped and chunked (see {@see WebsiteIngestionJob}). To discover the URLs
 * again first, use {@see RecrawlSourceWebsiteAction}.
 */
class RetrainSourceWebsiteAction
{
    public function execute(SourceWebsite $website, Agent $agent): void
    {
        WebsiteIngestionJob::dispatch($website, $agent);
    }
}
