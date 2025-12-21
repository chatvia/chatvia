<?php

namespace App\Services\Ingestion\Sources;

use App\Data\Ingestion\ChunkIngestionData;
use App\Data\Ingestion\OCR\PageData;
use App\Events\UrlIngestionEvent;
use App\Models\Agent;
use App\Models\AgentWebsiteUrl;
use App\Models\SourceWebsite;
use App\Services\Chunking\Chunker;
use App\Services\Splitters\RecursiveCharacterTextSplitter;
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
                return ! $response->ok() || $response->noContent();
            })
            ->mapWithKeys(function (Response $response, string $url) use ($splitter, $parser, $urls) {
                $text = $parser->getTextFromHtml($response->body());
                $checksum = md5($text);

                $urlModel = $urls->where('url', $url)->first();

                $status = 'ingesting';

                //                if ($urlModel->checksum === $checksum.'x') {
                //                    $chunks = collect();
                //                    $status = 'ingested';
                //                } else {
                $chunks = $splitter->splitText($text);
                $chunks = collect($chunks)
                    ->map(function ($textChunk) {
                        return new PageData(
                            pageNumber: 0,
                            content: $textChunk,
                        );
                    });

                $chunks = new Chunker(
                    agent: $this->agent,
                    chunkable: $this->website,
                )->createChunks($chunks);

                // Filter out chunks below 128 tokens
                $chunks = $chunks->reject(function (ChunkIngestionData $chunk) {
                    return TikToken::countTokens($chunk->chunk_text) < 128;
                });

                //                }

                $urlModel->update([
                    'checksum' => $checksum,
                    'crawled_at' => now(),
                    'status' => $status,
                ]);

                //                if ($status === 'ingested') {
                //                    UrlIngestionEvent::dispatch($urlModel, $this->agent->external_id);
                //                }

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
