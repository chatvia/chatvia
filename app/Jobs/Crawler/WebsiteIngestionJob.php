<?php

namespace App\Jobs\Crawler;

use App\Events\WebsiteCrawlerEvent;
use App\Jobs\Ingestion\IngestionChunkJob;
use App\Jobs\Ingestion\IngestionSummaryJob;
use App\Models\AgentWebsite;
use App\Models\AgentWebsiteUrl;
use App\Services\HTMLCleaner;
use App\Services\Ingestion\RecursiveCharacterTextSplitter;
use App\Services\Ingestion\Sources\WebsiteIngestion;
use App\Support\PromptLoader;
use Illuminate\Bus\Batch;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;

class WebsiteIngestionJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable;

    public function __construct(public AgentWebsite $website) {}

    public function handle(RecursiveCharacterTextSplitter $splitter, HTMLCleaner $parser): void
    {
        $this->website->update(['status' => 'ingesting']);

        $this->website->refresh();

        WebsiteCrawlerEvent::dispatch($this->website);

        /** @var WebsiteIngestion $websiteIngestion */
        $websiteIngestion = app(WebsiteIngestion::class, ['website' => $this->website]);

        /** @var AgentWebsiteUrl<array> $urls */
        $urls = $this->website->untrainedUrls;

        // Create a mapping of URL to Url ID
        $urlToId = $urls->pluck('id', 'url')->toArray();

        $urlsWithChunks = $websiteIngestion->chunkUrls();

        $jobs = [];

        $prompt = PromptLoader::load('chunking/web');

        $checksums = [];
        foreach ($urlsWithChunks as $url => $data) {
            foreach ($data['chunks'] as $chunk) {
                $jobs[] = new IngestionChunkJob($chunk, $prompt, chunkReference: [
                    'referenceable_type' => AgentWebsiteUrl::class,
                    'referenceable_id' => $urlToId[$url],
                ]);
            }
            $checksums[] = $data['checksum'];
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
        $text = $parser->getTextFromHtml($websiteHome->body());

        dispatch(new IngestionSummaryJob($this->website, $text));

        $totalChunks = $urlsWithChunks->sum(fn ($data) => count($data['chunks']));

        $this->website->update([
            'internal_ingestion_job_id' => $this->job->getJobId(),
            'internal_chunking_batch_id' => $batch->id,
            'expected_chunks' => $totalChunks,
            'checksum' => md5(collect($checksums)->implode('')),
        ]);
    }
}
