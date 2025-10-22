<?php

namespace App\Jobs\Crawler;

use App\Events\UrlIngestionEvent;
use App\Jobs\Ingestion\IngestionChunkJob;
use App\Models\AgentWebsite;
use App\Models\AgentWebsiteUrl;
use App\Services\Chunker;
use App\Services\Crawler\HTMLParser;
use App\Services\Ingestion\RecursiveCharacterTextSplitter;
use App\Support\PromptLoader;
use Illuminate\Bus\Batch;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;

class WebsiteUrlTrainJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public AgentWebsiteUrl $websiteUrl) {}

    public function handle(HTMLParser $parser, RecursiveCharacterTextSplitter $splitter): void
    {
        $response = Http::retry(3, 1000)->get($this->websiteUrl->url)->throw();

        $text = $parser->getTextFromHtml($response->body());
        $checksum = md5($text);

        if ($checksum === $this->websiteUrl->checksum) {
            $this->websiteUrl->update(['status' => 'ingested']);
            UrlIngestionEvent::dispatch($this->websiteUrl);

            return;
        }

        /** @var AgentWebsite $website */
        $website = $this->websiteUrl->agentWebsite;

        $deletedChunks = $website->chunks()->count();

        $this->websiteUrl->chunks()->delete();

        $chunks = $splitter->splitText($text);
        $chunks = collect($chunks)->map(fn ($item) => ['text' => $item])->toArray();
        $chunks = new Chunker(
            agentId: $website->agent->id,
            chunkableType: AgentWebsiteUrl::class,
            chunkableId: $this->websiteUrl->id,
        )->createChunks($chunks);

        $this->websiteUrl->update([
            'checksum' => $checksum,
            'crawled_at' => now(),
            'status' => 'ingesting',
        ]);

        $website->update([
            'expected_chunks' => ($website->expected_chunks - $deletedChunks + count($chunks)),
        ]);

        UrlIngestionEvent::dispatch($this->websiteUrl);

        $prompt = PromptLoader::load('chunking/web');

        $jobs = [];

        foreach ($chunks as $chunk) {
            $jobs[] = new IngestionChunkJob($chunk, $prompt, chunkReference: [
                'referenceable_type' => AgentWebsiteUrl::class,
                'referenceable_id' => $this->websiteUrl->id,
            ]);
        }

        $websiteUrlId = $this->websiteUrl->id;

        $batch = Bus::batch($jobs)
            ->name('Ingestion Agent WebSite URL ID: '.$websiteUrlId)
            ->then(function (Batch $batch) use ($websiteUrlId) {
                $url = AgentWebsiteUrl::where('url', $websiteUrlId)->first();
                $url->update(['status' => 'ingested']);
            })
            ->dispatch();

        $this->websiteUrl->update([
            'internal_ingestion_job_id' => $this->job->getJobId(),
            'internal_chunking_batch_id' => $batch->id,
        ]);
    }
}
