<?php

namespace Domain\Knowledge\Ingestion\Services\Sources;

use Domain\Knowledge\Ingestion\Services\Tidy\HTMLCleaner;
use Domain\Knowledge\Source\Web\Models\AgentWebsiteUrl;
use Domain\Knowledge\Source\Web\Models\SourceWebsite;
use Domain\Knowledge\Ingestion\Services\Chunking\Chunker;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\LazyCollection;

class WebsiteChunker
{
    public function __construct(private SourceWebsite $website) {}

    public function chunkUrls()
    {
        $untrainedUrls = $this->website->untrainedUrls;
        $urlsWithChunks = LazyCollection::make($this->getResponses())
            ->reject(function ($response) {
                if ($response instanceof ConnectionException) {
                    report($response);

                    return true;
                }

                return ! $response->ok() || $response->noContent();
            })
            ->mapWithKeys(function (Response $response, string $url) use ($untrainedUrls) {
                $webPageText = app(HTMLCleaner::class)->getTextFromHtml($response->body());

                $chunks = app(Chunker::class)->chunkHtml($webPageText);

                $checksum = hash('xxh128', $webPageText);

                $urlModel = $untrainedUrls->where('url', $url)->first();

                $status = 'ingesting';

                $urlModel->update([
                    'checksum' => $checksum,
                    'status' => $status,
                    'crawled_at' => now(),
                ]);

                return [
                    $url => [
                        'chunks' => $chunks,
                        'checksum' => $checksum,
                    ],
                ];
            });

        //        $totalChunks = $urlsWithChunks->sum(fn ($data) => count($data['chunks']));

        $totalChunks = count($untrainedUrls);

        // Append the total expected chunks to each chunk
        return $urlsWithChunks->mapWithKeys(function ($data, string $url) use ($totalChunks) {
            $data['expected_chunks'] = $totalChunks;

            return [$url => $data];
        });
    }

    public function getResponses(): array
    {
        $urls = $this->website->untrainedUrls;

        // Get all the responses for the given $urls
        return Http::pool(function (Pool $pool) use ($urls) {
            return $urls->map(function (AgentWebsiteUrl $websiteUrl) use ($pool): void {
                $pool->as($websiteUrl->url)->retry(3, 1000)->get($websiteUrl->url);
            });
        });
    }
}
