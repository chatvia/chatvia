<?php

namespace Domain\Knowledge\Source\Web\Services;

use Illuminate\Support\Facades\Cache;

/**
 * Bridges the two ingestion stages so a page is fetched from FireCrawl only
 * once.
 *
 * Stage 1 (the `/crawl`) already renders every page to Markdown while
 * discovering URLs. We stash that Markdown here keyed by URL; stage 2
 * (TrainWebsiteUrlAction) reads it back instead of spending a second FireCrawl
 * credit on `/scrape`. A miss (cache expired, or a one-off retrain of a single
 * URL that never went through a crawl) falls back to a live scrape, so this is
 * a cost optimisation, never a correctness dependency.
 */
final class WebPageMarkdownCache
{
    private const PREFIX = 'ingestion:website:markdown:';

    public function put(string $url, string $markdown): void
    {
        Cache::put($this->key($url), $markdown, now()->addHours($this->ttlHours()));
    }

    public function get(string $url): ?string
    {
        $value = Cache::get($this->key($url));

        return is_string($value) ? $value : null;
    }

    /**
     * Normalise the trailing slash so a crawl that stores `…/about/` and a URL
     * row persisted as `…/about` still hit the same entry.
     */
    private function key(string $url): string
    {
        return self::PREFIX.hash('sha256', rtrim($url, '/'));
    }

    private function ttlHours(): int
    {
        return max(1, (int) config('services.firecrawl.markdown_cache_hours', 48));
    }
}
