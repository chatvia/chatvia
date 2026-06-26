<?php

namespace Domain\Knowledge\Source\Web\Services\Crawler;

use Domain\Knowledge\Source\Web\Data\Crawler\CrawlerData;

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
    public const DEFAULT_LIMIT = 1000;

    /**
     * Reduce raw crawl pages to the final ingest set, preserving each URL's
     * Markdown so the caller can warm the scrape cache in one pass.
     *
     * @param  array<string, string>  $pages  url => markdown, as returned by Firecrawl::fetchCrawl()
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
            $normalized = rtrim($link, '/');

            if (isset($seen[$normalized])) {
                continue;
            }

            $seen[$normalized] = true;
            $result[$link] = $pages[$link];
        }

        return $result;
    }

    private function filterUrls(array $links, ?string $includePaths = null, ?string $excludePaths = null): array
    {
        if (empty($links)) {
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
        $parsedUrl = parse_url($url);
        $path = $parsedUrl['path'] ?? '/';

        return ltrim($path, '/');
    }

    private function matchesPatterns(string $urlPath, string $patterns): bool
    {
        $patternList = array_map('trim', explode(',', $patterns));

        foreach ($patternList as $pattern) {
            $pattern = ltrim($pattern, '/');
            $regexPattern = $this->convertGlobToRegex($pattern);

            if (preg_match($regexPattern, $urlPath)) {
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
        $regex = str_replace('\*', '.*', $escaped);

        // Wrap in delimiters and anchors
        return '#^'.$regex.'#';
    }
}
