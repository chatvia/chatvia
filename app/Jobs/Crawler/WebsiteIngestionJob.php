<?php

namespace App\Jobs\Crawler;

use App\Events\UrlIngestionEvent;
use App\Events\WebsiteCrawlerEvent;
use App\Events\WebsiteIngestionProgressEvent;
use App\Jobs\Ingestion\ChunkIngestionJob;
use App\Jobs\Ingestion\SummaryIngestionJob;
use App\Models\Agent;
use App\Models\AgentWebsiteUrl;
use App\Models\SourceWebsite;
use App\Services\Ingestion\Sources\WebsiteIngestion;
use App\Services\Prompts\PromptLoader;
use App\Services\Splitters\RecursiveCharacterTextSplitter;
use App\Services\Tidy\HTMLCleaner;
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

    public bool $deleteWhenMissingModels = true;

    public function __construct(public SourceWebsite $website, public Agent $agent)
    {
        $this->onQueue('ingestion.website');
    }

    public function handle(RecursiveCharacterTextSplitter $splitter, HTMLCleaner $parser): void
    {
        $this->website->update(['status' => 'ingesting']);

        $this->website->refresh();

        WebsiteCrawlerEvent::dispatch($this->website, $this->agent);

        /** @var WebsiteIngestion $websiteIngestion */
        $websiteIngestion = app(WebsiteIngestion::class, ['website' => $this->website, 'agentId' => $this->agent->id]);

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
                $jobs[] = new ChunkIngestionJob($chunk, $prompt, chunkReference: [
                    'referenceable_type' => AgentWebsiteUrl::class,
                    'referenceable_id' => $urlToId[$url],
                ]);
            }
            $checksums[] = $data['checksum'];
        }

        $websiteId = $this->website->id;

        $agentId = $this->agent->id;

        $batch = Bus::batch($jobs)
            ->name('Ingestion Agent WebSite ID: '.$websiteId)
            ->then(function (Batch $batch) use ($websiteId, $agentId) {
                $website = SourceWebsite::find($websiteId);

                if ($website) {
                    $agent = Agent::find($agentId);

                    // Get URLs that are still in 'ingesting' status and update them
                    $urlsToUpdate = $website->urls()
                        ->where('status', 'ingesting')
                        ->get();

                    foreach ($urlsToUpdate as $url) {
                        $url->update(['status' => 'ingested', 'crawled_at' => now()]);
                        UrlIngestionEvent::dispatch($url, $agent->external_id);
                    }

                    // Update website status and dispatch final progress event
                    $website->update(['status' => 'ingested']);
                    WebsiteIngestionProgressEvent::dispatch($website, $agent->external_id);
                    WebsiteCrawlerEvent::dispatch($website, $agent);
                }
            })
            ->dispatch();

        $websiteHome = Http::get($this->website->url);
        $text = $parser->getTextFromHtml($websiteHome->body());

        dispatch(new SummaryIngestionJob($this->website, $text));

        $totalChunks = $urlsWithChunks->sum(fn ($data) => count($data['chunks']));

        $this->website->update([
            'internal_ingestion_job_id' => $this->job->getJobId(),
            'internal_chunking_batch_id' => $batch->id,
            'expected_chunks' => $totalChunks,
            'checksum' => md5(collect($checksums)->implode('')),
        ]);
    }
}
