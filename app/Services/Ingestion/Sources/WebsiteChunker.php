<?php

namespace App\Services\Ingestion\Sources;

use App\Data\Ingestion\ChunkIngestionData;
use App\Data\Ingestion\OCR\PageData;
use App\Events\UrlIngestionEvent;
use App\Models\Agent;
use App\Models\AgentWebsiteUrl;
use App\Models\SourceWebsite;
use App\Services\Chunking\Chunker;
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
        $parser = app(HTMLCleaner::class);
        $urls = $this->website->untrainedUrls;
        $chunkIndex = 1; // Track chunk index across all URLs

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
                $html = $response->body();
                $checksum = md5($html);

                $urlModel = $urls->where('url', $url)->first();

                $status = 'ingesting';

                // Skip chunking if content hasn't changed
                if ($urlModel->checksum === $checksum) {
                    $chunks = collect();
                    $status = 'ingested';
                } else {
                    // One webpage = one chunk (no splitting)
                    // The LLM will handle the full HTML content
                    $pageData = new PageData(
                        pageNumber: 0,
                        content: $html,
                    );

                    $chunks = new Chunker(
                        agent: $this->agent,
                        chunkable: $this->website,
                    )->createChunks(collect([$pageData]));

                    // Filter out chunks below 128 tokens (keep meaningful content)
                    $chunks = $chunks->reject(function (ChunkIngestionData $chunk) {
                        return TikToken::countTokens($chunk->chunk_text) < 128;
                    });

                    // Update chunk indices to be sequential across all URLs
                    $chunks = $chunks->map(function (ChunkIngestionData $chunk) use (&$chunkIndex) {
                        $updatedChunk = ChunkIngestionData::from(array_merge(
                            $chunk->toArray(),
                            ['chunk_index' => $chunkIndex++]
                        ));

                        return $updatedChunk;
                    });
                }

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

        $totalChunks = $urlsWithChunks->sum(fn ($data) => count($data['chunks']));

        // Append the total expected chunks to each chunk
        return $urlsWithChunks->mapWithKeys(function ($data, string $url) use ($totalChunks) {
            $data['expected_chunks'] = $totalChunks;

            return [$url => $data];
        });
    }
}
