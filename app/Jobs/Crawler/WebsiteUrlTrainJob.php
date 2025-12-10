<?php

namespace App\Jobs\Crawler;

use App\Events\UrlIngestionEvent;
use App\Jobs\Ingestion\ChunkIngestionJob;
use App\Models\Agent;
use App\Models\AgentWebsiteUrl;
use App\Models\SourceWebsite;
use App\Services\Chunking\Chunker;
use App\Services\Prompts\PromptLoader;
use App\Services\Splitters\RecursiveCharacterTextSplitter;
use App\Services\Tidy\HTMLCleaner;
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

    public function __construct(public AgentWebsiteUrl $websiteUrl, public int $agentId)
    {
        $this->onQueue('ingestion.website');
    }

    public function handle(HTMLCleaner $parser, RecursiveCharacterTextSplitter $splitter): void
    {
        $response = Http::retry(3, 1000)->get($this->websiteUrl->url)->throw();

        $text = $parser->getTextFromHtml($response->body());
        $checksum = md5($text);

        $agentExternalId = Agent::where('id', $this->agentId)->value('external_id');

        if ($checksum === $this->websiteUrl->checksum) {
            $this->websiteUrl->update(['status' => 'ingested']);
            UrlIngestionEvent::dispatch($this->websiteUrl, $agentExternalId);

            return;
        }

        /** @var SourceWebsite $website */
        $website = $this->websiteUrl->agentWebsite;

        $deletedChunks = $website->chunks()->count();

        $this->websiteUrl->chunks()->delete();

        $chunks = $splitter->splitText($text);
        $chunks = collect($chunks)->map(fn ($item) => ['text' => $item])->toArray();
        $chunks = new Chunker(
            agentId: $this->agentId,
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

        UrlIngestionEvent::dispatch($this->websiteUrl, $agentExternalId);

        $prompt = PromptLoader::load('chunking/web');

        $jobs = [];

        foreach ($chunks as $chunk) {
            $jobs[] = new ChunkIngestionJob($chunk, $prompt, chunkReference: [
                'referenceable_type' => AgentWebsiteUrl::class,
                'referenceable_id' => $this->websiteUrl->id,
            ]);
        }

        $websiteUrlId = $this->websiteUrl->id;

        $batch = Bus::batch($jobs)
            ->name('Ingestion Agent WebSite URL ID: '.$websiteUrlId)
            ->then(function (Batch $batch) use ($websiteUrlId) {
                $url = AgentWebsiteUrl::find($websiteUrlId);
                $url?->update(['status' => 'ingested', 'crawled_at' => now()]);
            })
            ->dispatch();

        $this->websiteUrl->update([
            'internal_ingestion_job_id' => $this->job->getJobId(),
            'internal_chunking_batch_id' => $batch->id,
        ]);
    }
}
