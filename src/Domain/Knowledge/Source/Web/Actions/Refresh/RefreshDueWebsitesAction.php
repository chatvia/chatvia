<?php

declare(strict_types=1);

namespace Domain\Knowledge\Source\Web\Actions\Refresh;

use Carbon\CarbonInterface;
use Domain\Agent\Models\Agent;
use Domain\Knowledge\Ingestion\Enums\TrainingTrigger;
use Domain\Knowledge\Ingestion\Jobs\Website\WebsiteIngestionJob;
use Domain\Knowledge\Source\Enums\SourceStatus;
use Domain\Knowledge\Source\Web\Data\Crawler\CrawlerData;
use Domain\Knowledge\Source\Web\Enums\RefreshInterval;
use Domain\Knowledge\Source\Web\Events\WebsiteCrawlStarted;
use Domain\Knowledge\Source\Web\Jobs\Crawler\WebsiteCrawlerJob;
use Domain\Knowledge\Source\Web\Models\SourceWebsite;
use Illuminate\Support\Facades\Bus;

/**
 * Crawls and trains again every website whose refresh interval has passed:
 * discovery finds new and removed pages, then training scrapes every page and
 * re-embeds only those that changed. A website busy crawling or training is
 * left for the next run, and one no agent uses is skipped. Runs hourly
 * (`chatvia:knowledge:refresh-websites`).
 */
class RefreshDueWebsitesAction
{
    /**
     * @return int how many websites were started
     */
    public function execute(?CarbonInterface $now = null): int
    {
        $now ??= now();
        $started = 0;

        SourceWebsite::query()
            // Written exactly as the predicate of `source_websites_refresh_due_idx`
            // (id) WHERE refresh_interval <> 'off', with the value inline, so
            // the planner can match the partial index; the id pages below then
            // read only the websites that refresh.
            ->whereRaw("refresh_interval <> '".RefreshInterval::Off->value."'")
            ->whereIn('status', [SourceStatus::Completed, SourceStatus::Crawled, SourceStatus::Failed])
            ->chunkById(100, function ($websites) use ($now, &$started): void {
                foreach ($websites as $website) {
                    /** @var SourceWebsite $website */
                    if (! $website->refresh_interval->isDue($website->refreshed_at, $now)) {
                        continue;
                    }

                    $agent = $website->agents()->orderBy('agents.id')->first();

                    if (! $agent instanceof Agent) {
                        continue;
                    }

                    $this->refresh($website, $agent, $now);
                    $started++;
                }
            });

        return $started;
    }

    private function refresh(SourceWebsite $website, Agent $agent, CarbonInterface $now): void
    {
        $website->update(['status' => SourceStatus::Crawling, 'refreshed_at' => $now]);

        // The options it was added with: path filters, page limit, location.
        $crawler = CrawlerData::from([...($website->options ?? []), 'url' => $website->url]);

        WebsiteCrawlStarted::dispatch($website, $agent->external_id);

        Bus::chain([
            new WebsiteCrawlerJob($website, $crawler, $agent),
            new WebsiteIngestionJob($website, $agent, TrainingTrigger::Scheduled),
        ])->dispatch();
    }
}
