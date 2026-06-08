<?php

namespace Domain\Knowledge\Source\Web\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Thin adapter over the FireCrawl v2 API.
 *
 * Stage 1 (discovery) uses `/map` to enumerate a site's URLs cheaply.
 * Stage 2 (ingestion) uses `/scrape` to fetch a single page already converted
 * to clean Markdown — FireCrawl performs the fetch from its own infrastructure,
 * so we never hit the target site ourselves.
 */
class Firecrawl
{
    private function client(): PendingRequest
    {
        $baseUrl = rtrim((string) config('services.firecrawl.endpoint'), '/').'/';

        return Http::withToken(config('services.firecrawl.key'))
            ->baseUrl($baseUrl)
            ->timeout(120)
            ->retry(3, 2000)
            ->acceptJson()
            ->throw();
    }

    /**
     * Discover the URLs of a site. Returns a flat list of URL strings.
     *
     * @return list<string>
     */
    public function map(string $url, ?int $limit = null): array
    {
        $payload = ['url' => $url];

        if ($limit !== null) {
            $payload['limit'] = $limit;
        }

        $response = $this->client()->post('map', $payload)->json();

        return collect($response['links'] ?? [])
            ->pluck('url')
            ->filter()
            ->values()
            ->all();
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
}
