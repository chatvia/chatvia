<?php

namespace Domain\Knowledge\Source\Web\Services\Crawler;

use Domain\Knowledge\Source\Web\Data\Crawler\CrawlerData;
use Illuminate\Support\Str;
use Support\Http\SafeUrl;

/**
 * Stage-1 URL selection. The actual discovery is done by FireCrawl's async
 * `/crawl` (see Firecrawl::startCrawl / fetchCrawl); this class narrows the raw
 * crawl results down to the set we ingest — applying the include/exclude path
 * globs locally (FireCrawl's own filters are regex, so we keep the existing glob
 * semantics here) and dropping trailing-slash duplicates.
 */
final class Crawler
{
    /**
     * Safety cap on how many URLs a single crawl may surface, so a sprawling
     * sitemap can't balloon into thousands of URL rows. The UI can still pass a
     * smaller explicit limit via CrawlerData.
     */
    public const int DEFAULT_LIMIT = 1000;

    /**
     * Reduce a seed URL to its origin (scheme://host[:port]) for discovery.
     *
     * FireCrawl's `/map` scopes discovery to the seed's path subtree, so a seed
     * like `https://site.com/en` returns nothing when the site keeps no `/en/*`
     * pages (a default-locale-at-root layout). We always map the whole site and
     * let filterPages() narrow it by path, so a pasted deep link still discovers
     * the full site instead of an empty subtree.
     */
    public function rootUrl(string $url): string
    {
        $uri = SafeUrl::parse($url);

        if (blank($uri?->host())) {
            return $url;
        }

        $scheme = $uri->scheme() ?? 'https';
        $port = $uri->port() !== null ? ":{$uri->port()}" : '';

        return "{$scheme}://{$uri->host()}{$port}";
    }

    /**
     * Reduce raw crawl pages to the final ingest set, preserving each URL's
     * Markdown so the caller can warm the scrape cache in one pass.
     *
     * @param  array<string, string>  $pages  url => markdown (empty when discovery returned URLs only)
     * @return array<string, string> filtered & de-duplicated url => markdown
     */
    public function filterPages(CrawlerData $data, array $pages): array
    {
        $links = array_keys($pages);

        if ($data->include_paths || $data->exclude_paths) {
            $links = $this->filterUrls($links, $data->include_paths, $data->exclude_paths);
        }

        $result = [];
        $seen = [];

        foreach ($links as $link) {
            $normalized = Str::rtrim($link, '/');

            if (isset($seen[$normalized])) {
                continue;
            }

            $seen[$normalized] = true;
            $result[$link] = $pages[$link];
        }

        return $result;
    }

    /**
     * @param  list<string>  $links
     * @return list<string> the links whose path passes the include/exclude globs
     */
    private function filterUrls(array $links, ?string $includePaths = null, ?string $excludePaths = null): array
    {
        if ($links === []) {
            return [];
        }

        $filteredUrls = [];

        foreach ($links as $link) {
            // Extract the path from the URL
            $urlPath = $this->extractUrlPath($link);

            // Check exclude patterns first
            if ($excludePaths && $this->matchesPatterns($urlPath, $excludePaths)) {
                continue;
            }

            // Check include patterns
            if ($includePaths) {
                if ($this->matchesPatterns($urlPath, $includePaths)) {
                    $filteredUrls[] = $link;
                }
            } else {
                $filteredUrls[] = $link;
            }
        }

        return $filteredUrls;
    }

    private function extractUrlPath(string $url): string
    {
        // The raw path: a trailing slash is part of what the patterns match.
        $path = SafeUrl::parse($url)?->getUri()->getPath() ?? '';

        return Str::ltrim($path, '/');
    }

    private function matchesPatterns(string $urlPath, string $patterns): bool
    {
        $patternList = Str::of($patterns)->explode(',')->map(fn (string $pattern): string => Str::trim($pattern));

        foreach ($patternList as $pattern) {
            $pattern = Str::ltrim($pattern, '/');
            $regexPattern = $this->convertGlobToRegex($pattern);

            if (Str::isMatch($regexPattern, $urlPath)) {
                return true;
            }
        }

        return false;
    }

    private function convertGlobToRegex(string $pattern): string
    {
        // Escape special regex characters except *
        $escaped = preg_quote($pattern, '#');

        // Convert * to regex equivalent
        $regex = Str::replace('\*', '.*', $escaped);

        // Wrap in delimiters and anchors
        return "#^{$regex}#";
    }
}
