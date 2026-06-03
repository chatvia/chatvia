<?php

namespace Domain\Knowledge\Source\Web\Services\Crawler;

use Psr\Http\Message\UriInterface;
use Spatie\Crawler\CrawlProfiles\CrawlInternalUrls;

class CrawlerProfile extends CrawlInternalUrls
{
    public function shouldCrawl(UriInterface $url): bool
    {
        $urlString = (string) $url;

        // Exclude other common unwanted paths
        $excludePatterns = [
            '/cdn-cgi/',
            '/wp-admin/',
            '/admin/',
            '/.well-known/',
            '/ajax/',
            '/login',
            '/logout',
            '/register',
        ];

        if (array_any($excludePatterns, fn ($pattern): bool => str_contains($urlString, $pattern))) {
            return false;
        }

        // Only crawl URLs from the same domain
        return parent::shouldCrawl($url);
    }
}
