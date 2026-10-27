<?php

namespace Domain\Knowledge\Source\Web\Actions\Crawl;

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Source\Enums\SourceStatus;
use Domain\Knowledge\Source\Web\Data\Crawler\CrawlerData;
use Domain\Knowledge\Source\Web\Events\WebsiteCrawlStarted;
use Domain\Knowledge\Source\Web\Jobs\Crawler\WebsiteCrawlerJob;
use Domain\Knowledge\Source\Web\Models\SourceWebsite;
use Domain\Knowledge\Source\Web\Services\SeedUrlProbe;
use Illuminate\Support\Facades\DB;
use Support\Exceptions\DomainValidationException;

/**
 * Adds a website as a knowledge source of an agent and starts discovering its
 * pages (see {@see WebsiteCrawlerJob}).
 *
 * The seed URL is fetched once to follow its redirect ({@see SeedUrlProbe},
 * which guards against SSRF). The website is keyed to the agent's owner,
 * so it belongs to the workspace, not to the teammate who added it.
 */
class StartAgentWebsiteCrawlAction
{
    public function __construct(
        private readonly SeedUrlProbe $probe,
    ) {}

    /**
     * @throws DomainValidationException
     */
    public function execute(Agent $agent, CrawlerData $data): SourceWebsite
    {
        if ($agent->websites()->where('url', $data->url)->exists()) {
            throw new DomainValidationException('url', __('This website has already been added to this agent.'));
        }

        // The target is only stored here; FireCrawl does the actual crawl.
        $location = $this->probe->redirectOf($data->url);

        if ($location !== null) {
            $data = new CrawlerData(
                url: $location,
                limit: $data->limit,
                include_paths: $data->include_paths,
                exclude_paths: $data->exclude_paths,
                country: $data->country,
                languages: $data->languages,
            );
        }

        $website = DB::transaction(function () use ($agent, $data): SourceWebsite {
            $website = SourceWebsite::query()->create([
                'url' => $data->url,
                // Knowledge belongs to the workspace, not whoever crawled it — key to
                // the agent's owner so an employee's deletion can't cascade it away.
                'workspace_id' => $agent->workspace_id,
                'status' => SourceStatus::Crawling,
                'options' => $data->exclude('url')->toArray(),
            ]);

            $agent->websites()->attach($website->id);

            return $website;
        });

        WebsiteCrawlStarted::dispatch($website, $agent->external_id);
        WebsiteCrawlerJob::dispatch($website, $data, $agent);

        return $website;
    }
}
