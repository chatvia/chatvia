<?php

namespace Domain\Knowledge\Source\Web\Services\Crawler;

use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Support\Exceptions\UnsafeUrlException;
use Support\Http\SafeUrl;

/**
 * Drops the discovered links a site no longer serves.
 *
 * FireCrawl's `/map` and the search engines remember a relaunched site's old
 * pages long after they are gone, so each selected link gets one HEAD request
 * before it is stored. Only a definite "gone" answer (404, 410) drops a link:
 * a redirect, a bot block (403), a server error or a timeout keeps it, since
 * FireCrawl's own fetch at train time may still succeed where this probe did
 * not. A link that fails the SSRF check is dropped too, because training
 * would refuse it anyway.
 */
final class LinkLiveness
{
    private const int REQUESTS_PER_POOL = 25;

    private const int TIMEOUT_SECONDS = 10;

    private const array GONE_STATUSES = [404, 410];

    /**
     * @param  list<string>  $urls
     * @return list<string> the links in their original order, without the gone ones
     */
    public function filter(array $urls): array
    {
        $live = [];

        foreach (collect($urls)->chunk(self::REQUESTS_PER_POOL) as $batch) {
            $responses = Http::pool(function (Pool $pool) use ($batch): void {
                foreach ($batch as $url) {
                    try {
                        $options = SafeUrl::pinnedClientOptions($url);
                    } catch (UnsafeUrlException) {
                        continue;
                    }

                    $pool->as($url)
                        ->withOptions($options)
                        ->withUserAgent('Chatvia/Crawler')
                        ->timeout(self::TIMEOUT_SECONDS)
                        ->head($url);
                }
            });

            foreach ($batch as $url) {
                if (! array_key_exists($url, $responses)) {
                    continue;
                }

                $response = $responses[$url];

                if ($response instanceof Response && collect(self::GONE_STATUSES)->containsStrict($response->status())) {
                    continue;
                }

                $live[] = $url;
            }
        }

        return $live;
    }
}
