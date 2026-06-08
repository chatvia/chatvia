<?php

namespace Domain\Knowledge\Source\Web\Services\Crawler;

use Domain\Knowledge\Source\Web\Data\Crawler\CrawlerData;
use Domain\Knowledge\Source\Web\Services\Firecrawl;

/**
 * Stage 1 URL discovery. Backed by FireCrawl's `/map` endpoint — it returns the
 * site's URLs in a single cheap call instead of a headless-browser crawl. The
 * include/exclude path globs are applied locally because `/map` has no glob
 * filter of its own.
 */
final class Crawler
{
    /**
     * Safety cap mirroring the previous headless crawler's total limit, so a
     * sprawling sitemap can't balloon into thousands of URL rows. The UI can
     * still pass a smaller explicit limit.
     */
    private const DEFAULT_LIMIT = 1000;

    public function __construct(
        private readonly Firecrawl $firecrawl,
    ) {}

    public function crawl(CrawlerData $data): array
    {
        $links = $this->firecrawl->map($data->url, $data->limit ?? self::DEFAULT_LIMIT);

        if ($data->include_paths || $data->exclude_paths) {
            $links = $this->filterUrls($links, $data->include_paths, $data->exclude_paths);
        }

        return collect($links)
            ->unique(static fn (string $link): string => rtrim($link, '/'))
            ->values()
            ->all();
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
