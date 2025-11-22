<?php

namespace App\Services\Ingestion\Sources;

use App\Events\UrlIngestionEvent;
use App\Models\AgentWebsiteUrl;
use App\Models\SourceWebsite;
use App\Services\Chunking\Chunker;
use App\Services\Splitters\RecursiveCharacterTextSplitter;
use App\Services\Tidy\HTMLCleaner;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class WebsiteIngestion
{
    public function __construct(private SourceWebsite $website, private int $agentId) {}

    public function getResponses()
    {
        /** @var AgentWebsiteUrl<array> $urls */
        $urls = $this->website->untrainedUrls;

        $responsesCacheId = 'agent:'.$this->agentId.':website:'.$this->website->id.':ingestion:responses';

        if (Cache::has($responsesCacheId)) {
            return Cache::get($responsesCacheId);
        }

        // First, we get all the responses for the given $urls
        $responses = Http::pool(function (Pool $pool) use ($urls) {
            return $urls->map(function (AgentWebsiteUrl $websiteUrl) use ($pool) {
                $pool->as($websiteUrl->url)->retry(1, 1000)->get($websiteUrl->url);
            });
        });

        Cache::put($responsesCacheId, $responses, now()->addHours(12));

        return $responses;
    }

    public function chunkUrls()
    {
        $splitter = app(RecursiveCharacterTextSplitter::class);
        $parser = app(HTMLCleaner::class);
        $urls = $this->website->untrainedUrls;

        $urlsWithChunks = collect($this->getResponses())
            ->reject(function ($response) {
                // Filter out exceptions first
                if ($response instanceof ConnectionException) {
                    report($response);

                    return true;
                }

                // Then filter out failed responses
                return ! $response->ok() || $response->noContent() || ! str_contains($response->header('content-type'), 'text/html');
            })
            ->mapWithKeys(function (Response $response, string $url) use ($splitter, $parser, $urls) {
                $text = $parser->getTextFromHtml($response->body());
                $checksum = md5($text);

                $urlModel = $urls->where('url', $url)->first();

                $status = 'ingesting';

                if ($urlModel->checksum === $checksum) {
                    $chunks = collect();
                    $status = 'ingested';
                } else {
                    $chunks = $splitter->splitText($text);
                    $chunks = collect($chunks)->map(fn ($item) => ['text' => $item])->toArray();
                    $chunks = new Chunker(
                        agentId: $this->agentId,
                        chunkableType: SourceWebsite::class,
                        chunkableId: $this->website->id,
                    )->createChunks($chunks);
                }

                $urlModel->update([
                    'checksum' => $checksum,
                    'crawled_at' => now(),
                    'status' => $status,
                ]);

                if ($status === 'ingested') {
                    UrlIngestionEvent::dispatch($urlModel);
                }

                return [
                    $url => [
                        'chunks' => $chunks,
                        'checksum' => $checksum,
                    ],
                ];
            });

        $totalChunks = $urlsWithChunks->sum(fn ($data) => count($data['chunks']));

        // Append the total expected chunks to each chunk
        return $urlsWithChunks->mapWithKeys(function ($data, string $url) use ($totalChunks) {
            $data['expected_chunks'] = $totalChunks;

            return [$url => $data];
        });
    }
}
