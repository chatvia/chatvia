<?php

namespace Domain\Knowledge\Ingestion\Actions\Website;

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Embedding\Contracts\EmbeddingsProviderInterface;
use Domain\Knowledge\Ingestion\Jobs\Website\WebsiteUrlEnhanceJob;
use Domain\Knowledge\Ingestion\Models\Chunk;
use Domain\Knowledge\Ingestion\Services\Search\Bm25Tokenizer;
use Domain\Knowledge\Ingestion\Services\Tidy\HTMLCleaner;
use Domain\Knowledge\Ingestion\Services\TikToken;
use Domain\Knowledge\Source\Web\Events\UrlIngestionStarted;
use Domain\Knowledge\Source\Web\Events\WebsiteIngestionProgressed;
use Domain\Knowledge\Source\Web\Models\AgentWebsiteUrl;
use Domain\Knowledge\Source\Web\Models\SourceWebsite;
use Domain\Llm\Operations\Documents\Chunker;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class TrainWebsiteUrlAction
{
    private const PROGRESS_EVENT_EVERY = 25;

    public function __construct(
        private readonly HTMLCleaner $parser,
        private readonly Chunker $chunker,
        private readonly EmbeddingsProviderInterface $embeddings,
    ) {}

    public function execute(AgentWebsiteUrl $websiteUrl, Agent $agent): void
    {
        $response = Http::retry(3, 1000)
            ->timeout(600)
            ->get($websiteUrl->url)
            ->throw();

        $text = $this->parser->getTextFromHtml($response->body());

        $checksum = hash('sha256', $text);

        if ($websiteUrl->checksum === $checksum) {
            $websiteUrl->update(['status' => 'ingested']);
            UrlIngestionStarted::dispatch($websiteUrl, $agent->external_id);

            return;
        }

        $cacheKey = 'ingestion:website:chunks:'.$websiteUrl->id.':'.$checksum;

        $chunks = Cache::remember($cacheKey, now()->addDay(), function () use ($text) {
            return $this->chunker->chunkHtml($text);
        });

        /** @var SourceWebsite $website */
        $website = $websiteUrl->agentWebsite;

        foreach ($chunks as $idx => $chunk) {
            $chunkChecksum = hash('sha256', $chunk);

            $chunkModel = Chunk::query()->updateOrCreate(
                [
                    'chunkable_type' => $website->getMorphClass(),
                    'chunkable_id' => $website->id,
                    'checksum' => $chunkChecksum,
                ],
                [
                    'user_id' => $agent->user_id,
                    'type' => 'text',
                    'chunk_index' => $idx + 1,
                    'chunk' => $chunk,
                    'summary' => null,
                    'chunked_using_model' => config('chatvia.ai.models.chunker'),
                    'embedding_model' => $this->embeddings->getModelName(),
                    'cost_in_micro_cents' => 0,
                    'tokens' => TikToken::countTokens($chunk),
                    'embedding' => embed($chunk, true),
                    'embedding_bm25' => Bm25Tokenizer::expression($chunk),
                    'metadata' => [],
                ]
            );

            $chunkModel->references()->firstOrCreate([
                'referenceable_type' => $websiteUrl::class,
                'referenceable_id' => $websiteUrl->id,
            ]);

            WebsiteUrlEnhanceJob::dispatchIf(
                config('chatvia.ai.chunker.enhance') === true,
                $agent,
                $websiteUrl,
                $chunkModel,
            );

            if (($idx + 1) % self::PROGRESS_EVENT_EVERY === 0) {
                WebsiteIngestionProgressed::dispatch($website, $agent->external_id);
            }
        }

        DB::transaction(function () use ($website, $chunks): void {
            $locked = SourceWebsite::query()
                ->whereKey($website->id)
                ->lockForUpdate()
                ->first();

            if (! $locked) {
                return;
            }

            $locked->increment('expected_chunks', count($chunks));
        });

        $websiteUrl->update([
            'status' => 'ingested',
            'checksum' => $checksum,
        ]);

        $website->refresh();

        UrlIngestionStarted::dispatch($websiteUrl, $agent->external_id);
        WebsiteIngestionProgressed::dispatch($website, $agent->external_id);
    }
}
