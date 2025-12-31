<?php

namespace App\Jobs\Ingestion\Website;

use App\Contracts\EmbeddingsProviderInterface;
use App\Events\UrlIngestionEvent;
use App\Models\Agent;
use App\Models\AgentWebsiteUrl;
use App\Models\Chunk;
use App\Models\SourceWebsite;
use App\Services\Infrastructure\LLM\Operations\Chunker;
use App\Services\Tidy\HTMLCleaner;
use App\Services\TikToken;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class WebsiteUrlTrainJob implements ShouldBeUnique, ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public bool $deleteWhenMissingModels = true;

    public int $timeout = 800;

    public function __construct(public AgentWebsiteUrl $websiteUrl, public Agent $agent)
    {
        $this->onQueue('ingestion.website');
    }

    public function handle(HTMLCleaner $parser, Chunker $chunker): void
    {
        $response = Http::retry(3, 1000)
            ->timeout(600)
            ->get($this->websiteUrl->url)
            ->throw();

        $text = $parser->getTextFromHtml($response->body());
        $checksum = hash('xxh128', $text);

        if ($checksum === $this->websiteUrl->checksum) {
            $this->websiteUrl->update(['status' => 'ingested']);
            UrlIngestionEvent::dispatch($this->websiteUrl, $this->agent->external_id);

            return;
        }

        $chunks = $chunker->chunkHtml($text);

        /** @var SourceWebsite $website */
        $website = $this->websiteUrl->agentWebsite;

        foreach ($chunks as $idx => $chunk) {
            $chunkModel = Chunk::query()->create([
                'user_id' => $this->agent->user_id,
                'chunkable_type' => $website::class,
                'chunkable_id' => $website->id,
                'type' => 'text',
                'chunk_index' => $idx + 1,
                'chunk' => $chunk,
                'questions' => [],
                'summary' => '',
                'chunked_using_model' => config('ai.models.chunker'),
                'embedding_model' => app(EmbeddingsProviderInterface::class)->getModelName(),
                'cost_in_cents' => 0,
                'tokens' => TikToken::countTokens($chunk),
                'embedding' => embed($chunk, true),
                'embedding_bm25' => DB::raw('tokenize('.DB::connection()->getPdo()->quote($chunk).", 'llmlingua2')"),
                'metadata' => [],
                'checksum' => hash('xxh128', $chunk),
                'retrieval_count' => 0,
            ]);

            $chunkModel->references()->create([
                'referenceable_type' => $this->websiteUrl::class,
                'referenceable_id' => $this->websiteUrl->id,
            ]);

            WebsiteUrlEnhanceJob::dispatch($this->agent, $this->websiteUrl, $chunkModel);
        }

        $website->increment('expected_chunks', count($chunks));

        $this->websiteUrl->update(['status' => 'ingested']);

        UrlIngestionEvent::dispatch($this->websiteUrl, $this->agent->external_id);
    }

    public function uniqueId(): string
    {
        return 'agents:'.$this->agent->id.':website-url:'.$this->websiteUrl->id.':train';
    }
}
