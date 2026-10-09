<?php

declare(strict_types=1);

namespace Domain\Knowledge\Source\Web\Actions\Url;

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Ingestion\Jobs\Website\WebsiteUrlTrainJob;
use Domain\Knowledge\Source\Enums\SourceStatus;
use Domain\Knowledge\Source\Web\Models\AgentWebsiteUrl;

/**
 * Scrapes and chunks one page of a website again (for example after the page
 * changed). Unchanged content is detected by checksum and costs no embedding.
 */
class RetrainWebsiteUrlAction
{
    public function execute(AgentWebsiteUrl $url, Agent $agent): void
    {
        $url->update(['status' => SourceStatus::Pending]);

        WebsiteUrlTrainJob::dispatch($url, $agent);
    }
}
