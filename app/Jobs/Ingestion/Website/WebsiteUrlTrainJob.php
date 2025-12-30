<?php

namespace App\Jobs\Ingestion\Website;

use App\Data\Ingestion\ChunkIngestionData;
use App\Events\UrlIngestionEvent;
use App\Jobs\Ingestion\ChunkIngestionJob;
use App\Models\Agent;
use App\Models\AgentWebsiteUrl;
use App\Models\SourceWebsite;
use App\Services\Prompts\PromptLoader;
use App\Services\Tidy\HTMLCleaner;
use App\Services\TikToken;
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

    public bool $deleteWhenMissingModels = true;

    public int $timeout = 800;

    public function __construct(public AgentWebsiteUrl $websiteUrl, public Agent $agent)
    {
        $this->onQueue('ingestion.website');
    }

    public function uniqueId(): string
    {
        return 'agents:'.$this->agent->id.':website-url:'.$this->websiteUrl->id.':train';
    }

    public function handle(HTMLCleaner $parser): void
    {
        $response = Http::retry(3, 1000)->timeout(600)->get($this->websiteUrl->url)->throw();

        $html = $parser->cleanHtml($response->body());
        $checksum = md5($html);

        if ($checksum === $this->websiteUrl->checksum) {
            $this->websiteUrl->update(['status' => 'ingested']);
            UrlIngestionEvent::dispatch($this->websiteUrl, $this->agent->external_id);

            return;
        }

        /** @var SourceWebsite $website */
        $website = $this->websiteUrl->agentWebsite;

        $deletedChunks = $website->chunks()->count();

        $this->websiteUrl->chunks()->delete();

        // Check if content is meaningful (at least 128 tokens)
        $tokenCount = TikToken::countTokens($html);

        if ($tokenCount < 128) {
            // Skip pages with too little content
            $this->websiteUrl->update(['status' => 'ingested', 'checksum' => $checksum]);
            UrlIngestionEvent::dispatch($this->websiteUrl, $this->agent->external_id);

            return;
        }

        // One webpage = one chunk (no Chunker needed)
        $chunk = ChunkIngestionData::from([
            'agent_id' => $this->agent->id,
            'chunkable_type' => $this->websiteUrl::class,
            'chunkable_id' => $this->websiteUrl->id,
            'chunk_index' => 1, // Single URL = single chunk, always index 1
            'expected_chunks' => 1,
            'chunk_text' => trim($html),
            'chunk_images' => [],
            'chunk_tables' => [],
            'prev_chunk' => '',
            'next_chunk' => '',
        ]);

        $chunks = collect([$chunk]);

        $this->websiteUrl->update([
            'checksum' => $checksum,
            'crawled_at' => now(),
            'status' => 'ingesting',
        ]);

        $website->update([
            'expected_chunks' => ($website->expected_chunks - $deletedChunks + count($chunks)),
        ]);

        UrlIngestionEvent::dispatch($this->websiteUrl, $this->agent->external_id);

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
