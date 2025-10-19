<?php

namespace App\Jobs\Crawler;

use App\Events\WebsiteCrawlerEvent;
use App\Jobs\Ingestion\IngestionChunkJob;
use App\Jobs\Ingestion\IngestionSummaryJob;
use App\Models\AgentWebsite;
use App\Models\AgentWebsiteUrl;
use App\Services\Chunker;
use App\Services\Crawler\HTMLParser;
use App\Services\Ingestion\RecursiveCharacterTextSplitter;
use App\Support\PromptLoader;
use Illuminate\Bus\Batch;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;

class WebsiteIngestionJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable;

    public function __construct(public AgentWebsite $website) {}

    public function handle(RecursiveCharacterTextSplitter $splitter, HTMLParser $parser): void
    {
        $this->website->update(['status' => 'ingesting']);

        $this->website->refresh();

        WebsiteCrawlerEvent::dispatch($this->website);

        /** @var AgentWebsiteUrl<array> $urls */
        $urls = $this->website->urls;

        // Create a mapping of URL to Url ID
        $urlToId = $urls->pluck('id', 'url')->toArray();

        // First, we get all the responses for the given $urls
        $responses = Http::pool(function (Pool $pool) use ($urls) {
            return $urls->map(function (AgentWebsiteUrl $websiteUrl) use ($pool) {
                $pool->as($websiteUrl->url)->retry(1, 1000)->get($websiteUrl->url);
            });
        });

        // Then we filter the responses, because we might have invalid responses
        $urlsWithChunks = collect($responses)
            ->reject(function ($response) {
                // Filter out exceptions first
                if ($response instanceof ConnectionException) {
                    report($response);

                    return true;
                }

                // Then filter out failed responses
                return ! $response->ok() ||
                    $response->noContent() ||
                    ! str_contains($response->header('content-type'), 'text/html');
            })
            ->mapWithKeys(function (Response $response, string $url) use ($splitter, $parser, $urls) {
                $text = $parser->getTextFromHtml($response->getBody());

                $chunks = $splitter->splitText($text);
                $chunks = collect($chunks)->map(fn ($item) => ['text' => $item])->toArray();
                $chunks = new Chunker($this->website)->createChunks($chunks);

                $checksum = md5($text);

                $urls->where('url', $url)->first()->update([
                    'checksum' => $checksum,
                    'crawled_at' => now(),
                ]);

                return [
                    $url => [
                        'chunks' => $chunks,
                        'checksum' => $checksum,
                    ],
                ];
            });

        $totalChunks = $urlsWithChunks->sum(fn ($data) => count($data['chunks']));

        // Append the total expected chunks to each chunk
        $urlsWithChunks = $urlsWithChunks->mapWithKeys(function ($data, string $url) use ($totalChunks) {
            $data['expected_chunks'] = $totalChunks;

            return [$url => $data];
        });

        $jobs = [];

        $prompt = PromptLoader::load('chunking/web');

        foreach ($urlsWithChunks as $url => $data) {
            foreach ($data['chunks'] as $chunk) {
                $jobs[] = new IngestionChunkJob($chunk, $prompt, chunkReference: [
                    'referenceable_type' => AgentWebsiteUrl::class,
                    'referenceable_id' => $urlToId[$url],
                ]);
            }
        }

        $websiteId = $this->website->id;

        $batch = Bus::batch($jobs)
            ->name('Ingestion Agent WebSite ID: '.$websiteId)
            ->then(function (Batch $batch) use ($websiteId) {
                AgentWebsite::find($websiteId)?->update(['status' => 'ingested']);
                WebsiteCrawlerEvent::dispatch(AgentWebsite::find($websiteId));
            })
            ->dispatch();

        $websiteHome = Http::get($this->website->url);
        $text = $parser->getTextFromHtml($websiteHome->getBody());

        dispatch(new IngestionSummaryJob($this->website, $text));

        $this->website->update([
            'internal_ingestion_job_id' => $this->job->getJobId(),
            'internal_chunking_batch_id' => $batch->id,
            'expected_chunks' => $totalChunks,
        ]);
    }
}
