<?php

namespace Domain\Knowledge\Source\Web\Services;

use Domain\Knowledge\Source\Web\Data\Firecrawl\ScrapedPageData;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Thin adapter over the FireCrawl v2 API.
 *
 * Stage 1 (discovery) uses the synchronous `/map` endpoint — one fast call that
 * returns the site's link list (sitemap + crawl heuristics combined), covering
 * every section and language variant with no async job to poll. `/map`
 * returns URLs only (no Markdown).
 *
 * Stage 2 (ingestion) uses `/scrape` to fetch a single page as Markdown. Because
 * `/map` captures no Markdown, every trained page is scraped on demand here.
 * FireCrawl performs every fetch from its own infrastructure, so we never hit
 * the target site ourselves.
 */
class Firecrawl
{
    private function client(): PendingRequest
    {
        $baseUrl = Str::rtrim((string) config('services.firecrawl.endpoint'), '/').'/';

        return Http::withToken(config('services.firecrawl.key'))
            ->timeout(120)
            ->retry(3, 2000)
            ->acceptJson()
            ->throw()
            ->baseUrl($baseUrl);
    }

    /**
     * Discover a site's URLs via the synchronous `/map` endpoint.
     *
     * `sitemap: include` blends the sitemap with FireCrawl's own discovery so
     * sections missing from the sitemap are still found; `includeSubdomains`
     * follows `www`/locale subdomains; `ignoreQueryParameters` collapses
     * tracking-param variants of the same page. `useIndex: false` and
     * `ignoreCache: true` keep FireCrawl from answering with the URLs it
     * remembers from earlier visits (its stored index, a cached search), which
     * outlive a relaunched site; search engines still list such pages, so the
     * caller also drops the links the site no longer serves.
     *
     * `$country` / `$languages` set FireCrawl's `location` (proxy country +
     * Accept-Language) for the fetch. This does NOT filter a path-localised site
     * — a `/de`, `/fr` prefixed site returns the same URLs regardless of language
     * — it only changes content for sites that vary by Accept-Language. Language
     * *selection* on path-localised sites is done from the discovered URL paths,
     * not here.
     *
     * @param  list<string>  $languages
     * @return list<string> discovered URLs, de-duplicated
     */
    public function map(string $url, ?string $country = null, array $languages = [], ?int $limit = null): array
    {
        $payload = [
            'url' => $url,
            'sitemap' => 'include',
            'includeSubdomains' => true,
            'ignoreQueryParameters' => true,
            'useIndex' => false,
            'ignoreCache' => true,
            'timeout' => (int) config('services.firecrawl.timeout', 60000),
        ];

        if ($limit !== null) {
            $payload['limit'] = $limit;
        }

        if ($country !== null || $languages !== []) {
            $payload['location'] = array_filter([
                'country' => $country,
                'languages' => $languages !== [] ? array_values($languages) : null,
            ], static fn ($value): bool => $value !== null);
        }

        $response = $this->client()->post('map', $payload)->json();

        $links = [];

        foreach ($response['links'] ?? [] as $link) {
            $href = is_array($link) ? ($link['url'] ?? null) : $link;

            if (filled($href) && ! is_array($href)) {
                $links[$href] = true;
            }
        }

        return array_keys($links);
    }

    /**
     * The links one page carries, as FireCrawl's `/scrape` `links` format
     * returns them (absolute, every `<a href>` on the page, same site or not).
     *
     * Seeds discovery for a site `/map` finds nothing on: no sitemap and no
     * search-engine coverage yet. One scrape credit.
     *
     * @return list<string>
     */
    public function links(string $url): array
    {
        $payload = [
            'url' => $url,
            'formats' => ['links'],
            'onlyMainContent' => false,
            'timeout' => (int) config('services.firecrawl.timeout', 60000),
        ];

        $response = $this->client()->post('scrape', $payload)->json();

        /** @var array<int, mixed> $links */
        $links = is_array($response['data']['links'] ?? null) ? $response['data']['links'] : [];

        return collect($links)
            ->filter(fn ($link): bool => filled($link) && ! is_array($link))
            ->map(fn ($link): string => (string) $link)
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
     */
    public function scrape(string $url): ScrapedPageData
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

        return new ScrapedPageData(
            markdown: (string) ($data['markdown'] ?? ''),
            metadata: is_array($data['metadata'] ?? null) ? $data['metadata'] : [],
        );
    }
}
