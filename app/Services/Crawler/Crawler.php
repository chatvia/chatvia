<?php

namespace App\Services\Crawler;

use App\Data\Crawler\CrawlerData;
use Spatie\Crawler\Crawler as CrawlerSpatie;

class Crawler
{
    private int $concurrency = 10;

    private int $delayBetweenRequests = 250;

    private int $totalCrawlLimit = 1000;

    public static function create(): static
    {
        return new static;
    }

    public function setConcurrency(int $concurrency): static
    {
        $this->concurrency = $concurrency;

        return $this;
    }

    public function setTotalCrawlLimit(int $limit): static
    {
        $this->totalCrawlLimit = $limit;

        return $this;
    }

    public function crawl(CrawlerData $data): array
    {
        $queue = new CrawlerQueue;

        $crawler = CrawlerSpatie::create()
            ->setCrawlObserver(new CrawlerObserver)
            ->setCrawlQueue($queue)
            ->setConcurrency($this->concurrency)
            ->setDelayBetweenRequests($data->slow_crawling ? 1000 : 0)
            ->doNotExecuteJavaScript()
            ->respectRobots()
            ->setTotalCrawlLimit($this->totalCrawlLimit)
            ->setCrawlProfile(new CrawlerProfile($data->url));

        if ($data->limit) {
            $crawler = $crawler->setTotalCrawlLimit($data->limit);
        }

        $crawler->startCrawling($data->url);

        $crawledLinks = array_keys($queue->getUrls());

        if ($data->include_paths || $data->exclude_paths) {
            $urls = collect($this->filterUrls($crawledLinks, $data->include_paths, $data->exclude_paths));
        } else {
            $urls = collect($crawledLinks);
        }

        $urls = $urls->unique(function (string $link) {
            return rtrim($link, '/');
        });

        return $urls->toArray();
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
