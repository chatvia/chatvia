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
     * Translate a comma-separated glob list (e.g. "blog/*, dev/*") into the regex
     * patterns FireCrawl's `/crawl` includePaths/excludePaths expect, so the crawl
     * is scoped at the source — FireCrawl never fetches the unwanted sections and
     * the link budget isn't spent on them — instead of only being trimmed locally
     * afterwards. FireCrawl matches each pattern against the URL pathname, so we
     * anchor at the path start and tolerate an optional leading slash. filterPages()
     * still applies the exact glob semantics as the final authority, so a looser
     * regex flavour on FireCrawl's side can only over-fetch, never drop a wanted page.
     *
     * @return list<string>
     */
    public function firecrawlPatterns(?string $paths): array
    {
        if ($paths === null || trim($paths) === '') {
            return [];
        }

        $patterns = [];

        foreach (array_map('trim', explode(',', $paths)) as $pattern) {
            $pattern = ltrim($pattern, '/');

            if ($pattern === '') {
                continue;
            }

            $patterns[] = '^/?'.$this->globToRegexBody($pattern);
        }

        return $patterns;
    }

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
        // Wrap the shared glob body in PHP delimiters and a start anchor.
        return '#^'.$this->globToRegexBody($pattern).'#';
    }

    /**
     * Glob → regex core shared by the local PHP filter and the FireCrawl patterns:
     * escape regex metacharacters, then turn the glob `*` back into `.*`. Returns a
     * bare, undelimited, unanchored body so each caller can wrap it for its own
     * engine (`#^…#` for preg_match, `^/?…` for FireCrawl).
     */
    private function globToRegexBody(string $pattern): string
    {
        $escaped = preg_quote($pattern, '#');

        return str_replace('\*', '.*', $escaped);
    }
}
