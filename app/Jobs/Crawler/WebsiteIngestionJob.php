<?php

namespace App\Jobs\Crawler;

use App\Data\Crawler\LinkData;
use App\Events\CrawlingWebsiteEvent;
use App\Jobs\Ingestion\IngestionChunkJob;
use App\Jobs\Ingestion\IngestionSummaryJob;
use App\Models\AgentWebsite;
use App\Services\Chunker;
use App\Services\Crawler\HTMLParser;
use App\Services\Ingestion\RecursiveCharacterTextSplitter;
use Illuminate\Bus\Batch;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class WebsiteIngestionJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public AgentWebsite $website) {}

    public function handle(RecursiveCharacterTextSplitter $splitter, HTMLParser $parser): void
    {
        $this->website->update(['status' => 'ingesting']);

        $this->website->refresh();

        CrawlingWebsiteEvent::dispatch($this->website);

        $links = LinkData::collect($this->website->links);

        $pool = Http::pool(function (Pool $pool) use ($links) {
            return collect($links)->map(function (LinkData $link) use ($pool) {
                $pool->as($link->link)->get($link->link);
            });
        });

        $linksFingerprints = [];

        $linksWithChunks = collect($pool)
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
            ->mapWithKeys(function (Response $response, string $url) use ($splitter, &$linksFingerprints, $parser) {
                $text = $parser->getTextFromHtml($response->getBody());

                $linksFingerprints[$url] = md5($text);

                $chunks = $splitter->splitText($text);
                $chunks = collect($chunks)->map(fn ($item) => ['text' => $item])->toArray();
                $chunks = new Chunker($this->website)->createChunks($chunks);

                return [
                    $url => $chunks,
                ];
            });

        $totalChunks = $linksWithChunks->flatten()->count();

        $linksWithChunks = $linksWithChunks->mapWithKeys(function ($chunks, string $url) use ($totalChunks) {
            return [$url => collect($chunks)->map(function ($chunk) use ($totalChunks) {
                $chunk->total_chunks = $totalChunks;

                return $chunk;
            })];
        });

        $jobs = [];

        $prompt = Str::of('Attention: The following chunks were created by a crawler from the website.')
            ->append('You must carefully use these chunks, keeping only relevant and necessary content.')
            ->append('Remove any irrelevant or unnecessary material. If the content does not provide any value, such as advertisements, banners, or filler text, remove it entirely.')
            ->append('If after cleaning the chunk contains no useful content, return an empty string ("") as the output.')
            ->append('You MUST NOT filter any data such as emails, phone numbers, or social media links. These are useful for data retrieval.')
            ->toString();

        foreach ($linksWithChunks as $link => $chunks) {
            foreach ($chunks as $chunk) {
                $jobs[] = new IngestionChunkJob($chunk, $prompt, [
                    'link' => $link,
                    'link_md5' => $linksFingerprints[$link]],
                );
            }
        }

        $websiteId = $this->website->id;

        $batch = Bus::batch($jobs)
            ->name('Ingestion Agent WebSite ID: '.$websiteId)
            ->then(function (Batch $batch) use ($websiteId) {
                AgentWebsite::find($websiteId)?->update(['status' => 'ingested']);
            })
            ->dispatch();

        $content = $linksWithChunks->first()->pluck('chunk_text')->implode("\n\n");
        dispatch(new IngestionSummaryJob($this->website, $content));

        $this->website->update([
            'internal_ingestion_job_id' => $this->job->getJobId(),
            'internal_chunking_batch_id' => $batch->id,
            'chunks' => $totalChunks,
        ]);
    }
}
