<?php

namespace Domain\Knowledge\Source\Web\Services\Crawler;

use Domain\Knowledge\Source\Web\Data\Crawler\CrawlerData;
use Spatie\Browsershot\Browsershot;
use Spatie\Crawler\Crawler as CrawlerSpatie;

final class Crawler
{
    private int $concurrency = 10;

    private int $totalCrawlLimit = 1000;

    public static function create(): self
    {
        return new self;
    }

    public function setConcurrency(int $concurrency): self
    {
        $this->concurrency = $concurrency;

        return $this;
    }

    public function setTotalCrawlLimit(int $limit): self
    {
        $this->totalCrawlLimit = $limit;

        return $this;
    }

    public function crawl(CrawlerData $data): array
    {
        $queue = new CrawlerQueue;

        $crawler = CrawlerSpatie::create()
            ->setCrawlQueue($queue)
            ->setCrawlObserver(new CrawlerObserver)
            ->setCrawlProfile(new CrawlerProfile($data->url))
            ->setConcurrency($this->concurrency)
            ->setTotalCrawlLimit($this->totalCrawlLimit)
            ->setDelayBetweenRequests($data->slow_crawling ? 1000 : 0)
            ->setBrowsershot($this->createBrowsershot())
//            ->executeJavaScript()
            ->respectRobots();

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

    private function createBrowsershot(): Browsershot
    {
        $browsershot = app(\Support\Browsershot\BrowsershotConfigurator::class)->configure(new Browsershot);

        // Add common Chrome flags for server environments
        $browsershot->addChromiumArguments([
            'no-sandbox',
            'disable-setuid-sandbox',
            'disable-dev-shm-usage',
            'disable-gpu',
        ]);

        return $browsershot;
    }
}
