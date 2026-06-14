<?php

namespace Domain\Knowledge\Source\Web\Actions\Crawl;

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Knowledge\Source\Web\Data\Crawler\CrawlerData;
use Domain\Knowledge\Source\Web\Events\WebsiteCrawlStarted;
use Domain\Knowledge\Source\Web\Jobs\Crawler\WebsiteCrawlerJob;
use Domain\Knowledge\Source\Web\Models\SourceWebsite;
use Illuminate\Support\Facades\Http;
use Support\Exceptions\DomainValidationException;
use Support\Exceptions\UnsafeUrlException;
use Support\Http\SafeUrl;
use Throwable;

class StartAgentWebsiteCrawlAction
{
    /**
     * @throws DomainValidationException
     */
    public function execute(Agent $agent, User $user, CrawlerData $data): SourceWebsite
    {
        if ($agent->websites()->where('url', $data->url)->exists()) {
            throw new DomainValidationException('url', 'This website has already been added to this agent.');
        }

        // SSRF guard on the seed URL before any server-side fetch.
        $this->assertSafe($data->url);

        try {
            $response = Http::withOptions(['allow_redirects' => false])->get($data->url)->throw();
        } catch (UnsafeUrlException $e) {
            throw new DomainValidationException('url', 'This URL is not allowed.');
        } catch (Throwable $throwable) {
            report($throwable);
            throw new DomainValidationException('url', 'Failed to fetch the website. Please check the URL and try again.');
        }

        if ($response->redirect()) {
            // Re-validate the upstream-controlled redirect target before adopting
            // it — a public seed must not 30x into an internal address.
            $location = (string) $response->header('Location');
            $this->assertSafe($location);
            $data->url = $location;
        }

        $website = SourceWebsite::create([
            'url' => $data->url,
            // Knowledge belongs to the workspace, not whoever crawled it — key to
            // the agent's owner so an employee's deletion can't cascade it away.
            'user_id' => $agent->user_id,
            'status' => 'crawling',
            'options' => $data->exclude('url')->toArray(),
        ]);

        $agent->websites()->attach($website->id);

        WebsiteCrawlStarted::dispatch($website, $agent);
        WebsiteCrawlerJob::dispatch($website, $data, $agent);

        return $website;
    }

    /**
     * @throws DomainValidationException
     */
    private function assertSafe(string $url): void
    {
        try {
            SafeUrl::assert($url);
        } catch (UnsafeUrlException $e) {
            throw new DomainValidationException('url', 'This URL is not allowed: '.$e->getMessage());
        }
    }
}
