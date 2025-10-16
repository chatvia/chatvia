<?php

namespace App\Jobs\Crawler;

use App\Events\WebsiteCrawlerEvent;
use App\Jobs\Ingestion\IngestionChunkJob;
use App\Jobs\Ingestion\IngestionSummaryJob;
use App\Models\AgentWebsite;
use App\Models\Link;
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

        $links = $this->website->links;

        // Create a mapping of URL to Link ID
        $linkUrlToId = $links->pluck('id', 'link');

        // First, we get all the responses for the given $links
        $responses = Http::pool(function (Pool $pool) use ($links) {
            return $links->map(function ($link) use ($pool) {
                $pool->as($link->link)->get($link->link);
            });
        });

        // Then we filter the responses, because we might have invalid responses
        $linksWithChunks = collect($responses)
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
            ->mapWithKeys(function (Response $response, string $link) use ($splitter, $parser) {
                $text = $parser->getTextFromHtml($response->getBody());

                $chunks = $splitter->splitText($text);
                $chunks = collect($chunks)->map(fn ($item) => ['text' => $item])->toArray();
                $chunks = new Chunker($this->website)->createChunks($chunks);

                return [
                    $link => $chunks,
                ];
            });

        $totalChunks = $linksWithChunks->flatten()->count();

        $linksWithChunks = $linksWithChunks->mapWithKeys(function ($chunks, string $link) use ($totalChunks) {
            return [$link => collect($chunks)->map(function ($chunk) use ($totalChunks) {
                $chunk->expected_chunks = $totalChunks;

                return $chunk;
            })];
        });

        $jobs = [];

        $prompt = PromptLoader::load('chunking/web');

        foreach ($linksWithChunks as $link => $chunks) {
            foreach ($chunks as $chunk) {
                $jobs[] = new IngestionChunkJob($chunk, $prompt, chunkReference: [
                    'referenceable_type' => Link::class,
                    'referenceable_id' => $linkUrlToId[$link],
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

        $content = $linksWithChunks->first()->pluck('chunk_text')->implode("\n\n");

        dispatch(new IngestionSummaryJob($this->website, $content));

        $this->website->update([
            'internal_ingestion_job_id' => $this->job->getJobId(),
            'internal_chunking_batch_id' => $batch->id,
            'expected_chunks' => $totalChunks,
        ]);
    }
}
