<?php

namespace Domain\Ingestion\Services\Sources;

use Domain\Sources\Web\Events\UrlIngestionEvent;
use Domain\Agents\Models\Agent;
use Domain\Sources\Web\Models\AgentWebsiteUrl;
use Domain\Sources\Web\Models\SourceWebsite;
use App\LLM\Operations\Chunker;
use Domain\Ingestion\Services\Tidy\HTMLCleaner;
use Domain\Ingestion\Services\TikToken;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\LazyCollection;

class WebsiteChunker
{
    public function __construct(private SourceWebsite $website, private Agent $agent) {}

    public function chunkUrls()
    {
        $untrainedUrls = $this->website->untrainedUrls;
        $chunkIndex = 1;

        $urlsWithChunks = LazyCollection::make($this->getResponses())
            ->reject(function ($response) {
                if ($response instanceof ConnectionException) {
                    report($response);

                    return true;
                }

                return ! $response->ok() || $response->noContent();
            })
            ->mapWithKeys(function (Response $response, string $url) use ($untrainedUrls, &$chunkIndex) {
                $webPageText = app(HTMLCleaner::class)->getTextFromHtml($response->getBody()->getContents());

                $chunks = app(Chunker::class)->chunkHtml($webPageText);

                $checksum = hash('xxh128', $webPageText);

                $urlModel = $untrainedUrls->where('url', $url)->first();

                $status = 'ingesting';

                $tokenCount = TikToken::countTokens($webPageText);

                $urlModel->update([
                    'checksum' => $checksum,
                    'status' => $status,
                    'crawled_at' => now(),
                ]);

                if ($status === 'ingested') {
                    UrlIngestionEvent::dispatch($urlModel, $this->agent->external_id);
                }

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
        /** @var array<int, AgentWebsiteUrl> $urls */
        $urls = $this->website->untrainedUrls;

        // Get all the responses for the given $urls
        return Http::pool(function (Pool $pool) use ($urls) {
            return $urls->map(function (AgentWebsiteUrl $websiteUrl) use ($pool) {
                $pool->as($websiteUrl->url)->retry(3, 1000)->get($websiteUrl->url);
            });
        });
    }
}
