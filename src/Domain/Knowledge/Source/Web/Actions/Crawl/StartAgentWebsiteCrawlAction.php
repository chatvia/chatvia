<?php

namespace Domain\Knowledge\Source\Web\Actions\Crawl;

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Source\Enums\SourceStatus;
use Domain\Knowledge\Source\Web\Data\Crawler\CrawlerData;
use Domain\Knowledge\Source\Web\Events\WebsiteCrawlStarted;
use Domain\Knowledge\Source\Web\Jobs\Crawler\WebsiteCrawlerJob;
use Domain\Knowledge\Source\Web\Models\SourceWebsite;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Support\Exceptions\DomainValidationException;
use Support\Exceptions\UnsafeUrlException;
use Support\Http\SafeUrl;
use Throwable;

/**
 * Adds a website as a knowledge source of an agent and starts discovering its
 * pages (see {@see WebsiteCrawlerJob}).
 *
 * The seed URL is fetched once from our own servers to follow its redirect,
 * so this is an SSRF surface: the URL and any redirect target must be public,
 * redirects are never followed by the client, and the request connects to the
 * exact address that was checked. The website is keyed to the agent's owner,
 * so it belongs to the workspace, not to the teammate who added it.
 */
class StartAgentWebsiteCrawlAction
{
    /**
     * @throws DomainValidationException
     */
    public function execute(Agent $agent, CrawlerData $data): SourceWebsite
    {
        if ($agent->websites()->where('url', $data->url)->exists()) {
            throw new DomainValidationException('url', __('This website has already been added to this agent.'));
        }

        // SSRF guard on the seed URL before any server-side fetch. The probe
        // connects to the exact addresses that were checked, so a DNS answer
        // that changes between the check and the request can't reach an
        // internal host.
        $clientOptions = $this->safely(fn (): array => SafeUrl::pinnedClientOptions($data->url));

        try {
            $response = Http::withOptions($clientOptions)
                ->connectTimeout(10)
                ->timeout(20)
                ->get($data->url)
                ->throw();
        } catch (Throwable $throwable) {
            report($throwable);
            throw new DomainValidationException('url', __('Failed to fetch the website. Please check the URL and try again.'));
        }

        if ($response->redirect()) {
            // Re-validate the upstream-controlled redirect target before adopting
            // it — a public seed must not 30x into an internal address. The
            // target is only stored here; FireCrawl does the actual crawl.
            $location = (string) $response->header('Location');
            $this->safely(fn () => SafeUrl::assert($location));
            $data->url = $location;
        }

        $website = DB::transaction(function () use ($agent, $data): SourceWebsite {
            $website = SourceWebsite::create([
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

    /**
     * Runs a SafeUrl check and turns its refusal into a validation error on
     * the `url` field.
     *
     * @template T
     *
     * @param  callable(): T  $check
     * @return T
     *
     * @throws DomainValidationException
     */
    private function safely(callable $check): mixed
    {
        try {
            return $check();
        } catch (UnsafeUrlException $e) {
            throw new DomainValidationException('url', __('This URL is not allowed: :reason', ['reason' => $e->getMessage()]));
        }
    }
}
