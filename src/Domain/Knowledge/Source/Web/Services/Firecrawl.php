<?php

namespace Domain\Knowledge\Source\Web\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Thin adapter over the FireCrawl v2 API.
 *
 * Stage 1 (discovery) uses the async `/crawl` endpoint — a full recursive crawl
 * that follows links across the whole domain, so every section and every
 * language variant is found. (The cheaper `/map` is sitemap-biased and shallow,
 * which left multi-language sites with only a random handful of URLs.) `/crawl`
 * is asynchronous: `startCrawl()` kicks it off and returns a job id, then
 * `fetchCrawl()` is polled until the job completes.
 *
 * Stage 2 (ingestion) uses `/scrape` to fetch a single page as Markdown — but
 * only as a fallback, since the crawl already produced that Markdown (see
 * WebPageMarkdownCache). FireCrawl performs every fetch from its own
 * infrastructure, so we never hit the target site ourselves.
 */
class Firecrawl
{
    /** Authenticated client without a base URL — used to follow absolute `next` cursors. */
    private function rawClient(): PendingRequest
    {
        return Http::withToken(config('services.firecrawl.key'))
            ->timeout(120)
            ->retry(3, 2000)
            ->acceptJson()
            ->throw();
    }

    private function client(): PendingRequest
    {
        $baseUrl = rtrim((string) config('services.firecrawl.endpoint'), '/').'/';

        return $this->rawClient()->baseUrl($baseUrl);
    }

    /**
     * Start an async crawl of the whole site and return FireCrawl's job id.
     *
     * `crawlEntireDomain` is the option that fixes shallow discovery: without it
     * FireCrawl only descends into deeper paths and skips sibling sections such
     * as /ar, /fa, /en hanging off the root — exactly the missing-language
     * symptom. `scrapeOptions` mirror the stage-2 `/scrape` call so the Markdown
     * captured here is equivalent to what a later scrape would produce.
     *
     * `$includePaths` / `$excludePaths` are regex patterns (already translated
     * from the UI's path globs by Crawler::firecrawlPatterns) matched against each
     * URL's pathname, so the crawl is scoped at the source rather than only being
     * trimmed locally afterwards — FireCrawl never fetches the unwanted sections
     * and the link budget isn't spent on them.
     *
     * @param  list<string>  $includePaths
     * @param  list<string>  $excludePaths
     */
    public function startCrawl(
        string $url,
        ?int $limit = null,
        bool $slowCrawling = false,
        array $includePaths = [],
        array $excludePaths = [],
    ): string {
        $scrapeOptions = [
            'formats' => ['markdown'],
            'onlyMainContent' => true,
            'timeout' => (int) config('services.firecrawl.timeout', 60000),
        ];

        $waitFor = (int) config('services.firecrawl.wait_for', 0);

        if ($waitFor > 0) {
            $scrapeOptions['waitFor'] = $waitFor;
        }

        $payload = [
            'url' => $url,
            'crawlEntireDomain' => true,
            'sitemap' => 'include',
            'ignoreQueryParameters' => true,
            'scrapeOptions' => $scrapeOptions,
        ];

        if ($limit !== null) {
            $payload['limit'] = $limit;
        }

        if ($includePaths !== []) {
            $payload['includePaths'] = $includePaths;
        }

        if ($excludePaths !== []) {
            $payload['excludePaths'] = $excludePaths;
        }

        if ($slowCrawling) {
            // One second between scrapes (FireCrawl forces concurrency to 1),
            // a gentler pace for small or rate-limited hosts.
            $payload['delay'] = 1;
        }

        $id = $this->client()->post('crawl', $payload)->json('id');

        if (! is_string($id) || $id === '') {
            throw new RuntimeException('FireCrawl /crawl did not return a job id.');
        }

        return $id;
    }

    /**
     * Fetch the current state of a crawl. While the crawl is still running this
     * returns just the status; once `completed` it walks the paginated result
     * set and returns every page's Markdown keyed by its URL.
     *
     * @return array{status: string, pages: array<string, string>}
     */
    public function fetchCrawl(string $id): array
    {
        $response = $this->client()->get('crawl/'.$id)->json();

        $status = (string) ($response['status'] ?? '');

        if ($status !== 'completed') {
            return ['status' => $status, 'pages' => []];
        }

        $pages = $this->extractPages($response);

        // Large crawls page their results behind an absolute `next` cursor.
        $next = $response['next'] ?? null;

        while (is_string($next) && $next !== '') {
            $response = $this->rawClient()->get($next)->json();
            $pages += $this->extractPages($response);
            $next = $response['next'] ?? null;
        }

        return ['status' => $status, 'pages' => $pages];
    }

    /**
     * Scrape a single page as Markdown.
     *
     * FireCrawl renders the page in a headless browser, so JavaScript-driven
     * content (SPAs, AJAX, client-side rendering) is captured. `waitFor` adds a
     * settle delay for content that loads after the initial render; `timeout`
     * caps a slow render.
     *
     * @return array{markdown: string, metadata: array<string, mixed>}
     */
    public function scrape(string $url): array
    {
        $payload = [
            'url' => $url,
            'formats' => ['markdown'],
            'onlyMainContent' => true,
            'timeout' => (int) config('services.firecrawl.timeout', 60000),
        ];

        $waitFor = (int) config('services.firecrawl.wait_for', 0);

        if ($waitFor > 0) {
            $payload['waitFor'] = $waitFor;
        }

        $response = $this->client()->post('scrape', $payload)->json();

        $data = $response['data'] ?? [];

        return [
            'markdown' => (string) ($data['markdown'] ?? ''),
            'metadata' => $data['metadata'] ?? [],
        ];
    }

    /**
     * Pull the `url => markdown` pairs out of a crawl result page.
     *
     * @param  array<string, mixed>  $response
     * @return array<string, string>
     */
    private function extractPages(array $response): array
    {
        $pages = [];

        foreach ($response['data'] ?? [] as $document) {
            $url = $document['metadata']['sourceURL'] ?? $document['metadata']['url'] ?? null;

            if (is_string($url) && $url !== '') {
                $pages[$url] = (string) ($document['markdown'] ?? '');
            }
        }

        return $pages;
    }
}
