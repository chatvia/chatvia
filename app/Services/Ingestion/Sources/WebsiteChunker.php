<?php

namespace App\Services\Ingestion\Sources;

use App\Data\Ingestion\ChunkIngestionData;
use App\Events\UrlIngestionEvent;
use App\Models\Agent;
use App\Models\AgentWebsiteUrl;
use App\Models\SourceWebsite;
use App\Services\Tidy\HTMLCleaner;
use App\Services\TikToken;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class WebsiteChunker
{
    public function __construct(private SourceWebsite $website, private Agent $agent) {}

    public function getResponses(): array
    {
        /** @var array<int, AgentWebsiteUrl> $urls */
        $urls = $this->website->untrainedUrls;

        // Get all the responses for the given $urls
        return Http::pool(function (Pool $pool) use ($urls) {
            return $urls->map(function (AgentWebsiteUrl $websiteUrl) use ($pool) {
                $pool->as($websiteUrl->url)->retry(1, 1000)->get($websiteUrl->url);
            });
        });
    }

    public function chunkUrls()
    {
        $urls = $this->website->untrainedUrls;
        $chunkIndex = 1;

        $urlsWithChunks = collect($this->getResponses())
            ->reject(function ($response) {
                // Filter out exceptions first
                if ($response instanceof ConnectionException) {
                    report($response);

                    return true;
                }

                // Then filter out failed responses
                return ! $response->ok() || $response->noContent();
            })
            ->mapWithKeys(function (Response $response, string $url) use ($urls, &$chunkIndex) {
                // Get clean HTML content
                $html = app(HTMLCleaner::class)->cleanHtml($response->getBody()->getContents());

                $checksum = md5($html);

                $urlModel = $urls->where('url', $url)->first();

                $status = 'ingesting';

                // Skip chunking if content hasn't changed
                //                if ($urlModel->checksum === $checksum) {
                //                    $chunks = collect();
                //                    $status = 'ingested';
                //                } else {
                //                    // Check if content is meaningful (at least 128 tokens)
                $tokenCount = TikToken::countTokens($html);

                if ($tokenCount < 128) {
                    // Skip pages with too little content
                    $chunks = collect();
                    $status = 'ingested';
                } else {
                    // One webpage = one chunk (no Chunker needed)
                    $chunk = ChunkIngestionData::from([
                        'agent_id' => $this->agent->id,
                        'chunkable_type' => $this->website::class,
                        'chunkable_id' => $this->website->id,
                        'chunk_index' => $chunkIndex++,
                        'expected_chunks' => 0, // Will be set later
                        'chunk_text' => trim($html),
                        'chunk_images' => [],
                        'chunk_tables' => [],
                        'prev_chunk' => '',
                        'next_chunk' => '',
                    ]);

                    $chunks = collect([$chunk]);
                }
                //                }

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

        $totalChunks = count($urls);

        // Append the total expected chunks to each chunk
        return $urlsWithChunks->mapWithKeys(function ($data, string $url) use ($totalChunks) {
            $data['expected_chunks'] = $totalChunks;

            return [$url => $data];
        });
    }
}
