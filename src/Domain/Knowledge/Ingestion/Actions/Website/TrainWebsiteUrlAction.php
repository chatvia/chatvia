<?php

namespace Domain\Knowledge\Ingestion\Actions\Website;

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Embedding\Contracts\EmbeddingsProviderInterface;
use Domain\Knowledge\Ingestion\Contracts\TextSplitter;
use Domain\Knowledge\Ingestion\Jobs\Website\WebsiteUrlEnhanceJob;
use Domain\Knowledge\Ingestion\Models\Chunk;
use Domain\Knowledge\Ingestion\Models\ChunkReference;
use Domain\Knowledge\Ingestion\Services\Search\Bm25Tokenizer;
use Domain\Knowledge\Source\Enums\SourceStatus;
use Domain\Knowledge\Source\Web\Events\UrlIngestionStarted;
use Domain\Knowledge\Source\Web\Events\WebsiteIngestionProgressed;
use Domain\Knowledge\Source\Web\Models\AgentWebsiteUrl;
use Domain\Knowledge\Source\Web\Models\SourceWebsite;
use Domain\Knowledge\Source\Web\Services\Firecrawl;
use Domain\Llm\Services\TikToken;
use Domain\Llm\Usage\TokenUsageContext;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Support\Http\SafeUrl;

class TrainWebsiteUrlAction
{
    private const int PROGRESS_EVENT_EVERY = 25;

    public function __construct(
        private readonly Firecrawl $firecrawl,
        private readonly TextSplitter $splitter,
        private readonly EmbeddingsProviderInterface $embeddings,
    ) {}

    public function execute(AgentWebsiteUrl $websiteUrl, Agent $agent): void
    {
        // FireCrawl performs the fetch from its own infrastructure, so there is
        // no server-side SSRF surface here — but we still refuse obviously
        // non-public targets before spending a scrape credit on them.
        SafeUrl::assert($websiteUrl->url);

        // Discovery (`/map`) returns URLs only, so every trained page is
        // scraped here.
        $markdown = trim($this->firecrawl->scrape($websiteUrl->url)['markdown']);

        // Nothing scrapable (empty page, blocked, JS-only with no content).
        if ($markdown === '') {
            $websiteUrl->update([
                'status' => SourceStatus::Completed->value,
                'crawled_at' => now(),
            ]);

            UrlIngestionStarted::dispatch($websiteUrl, $agent->external_id);

            return;
        }

        // FireCrawl is a scraping service, not an LLM, so it returns no `usage`
        // block — meter the scraped content with the same tokenizer used
        // everywhere else and add it to the workspace scope that the
        // TrackTokenUsage middleware opens around WebsiteUrlTrainJob. No-op
        // outside such a scope. Counted on every successful scrape, even when
        // the checksum is unchanged below.
        app(TokenUsageContext::class)->add(TikToken::countTokens($markdown));

        $checksum = hash('sha256', $markdown);

        if ($websiteUrl->checksum === $checksum) {
            $websiteUrl->update(['status' => SourceStatus::Completed->value]);
            UrlIngestionStarted::dispatch($websiteUrl, $agent->external_id);

            return;
        }

        $cacheKey = 'ingestion:website:chunks:'.$websiteUrl->id.':'.$checksum;

        $chunks = Cache::remember($cacheKey, now()->addDay(), function () use ($markdown) {
            return $this->splitter->split($markdown);
        });

        /** @var SourceWebsite $website */
        $website = $websiteUrl->agentWebsite;

        // Embed only checksums the source doesn't already hold — one batched request.
        $checksums = array_map(static fn (string $chunk): string => hash('sha256', $chunk), $chunks);

        // One query for the chunks this source already holds, keyed by checksum.
        $existing = Chunk::query()
            ->where('chunkable_type', $website->getMorphClass())
            ->where('chunkable_id', $website->id)
            ->whereIn('checksum', $checksums)
            ->get(['id', 'checksum', 'chunk_index'])
            ->keyBy('checksum');

        $missing = array_values(array_filter(
            $chunks,
            fn (int $idx): bool => ! $existing->has($checksums[$idx]),
            ARRAY_FILTER_USE_KEY,
        ));

        $vectors = [];
        foreach ($this->embeddings->embedBatch($missing) as $i => $vector) {
            $vectors[hash('sha256', $missing[$i])] = '['.implode(',', $vector).']';
        }

        $enhance = config('chatvia.ai.chunker.enhance') === true;
        $chunkModels = [];

        foreach ($chunks as $idx => $chunk) {
            $chunkChecksum = $checksums[$idx];

            if ($existing->has($chunkChecksum)) {
                /** @var Chunk $chunkModel */
                $chunkModel = $existing->get($chunkChecksum);
                if ($chunkModel->chunk_index !== $idx + 1) {
                    $chunkModel->update(['chunk_index' => $idx + 1]);
                }
            } else {
                $chunkModel = Chunk::query()->createOrFirst(
                    [
                        'chunkable_type' => $website->getMorphClass(),
                        'chunkable_id' => $website->id,
                        'checksum' => $chunkChecksum,
                    ],
                    [
                        'user_id' => $agent->user_id,
                        'chunk_index' => $idx + 1,
                        'chunk' => $chunk,
                        'summary' => null,
                        'chunked_using_model' => $this->splitter->name(),
                        'cost_in_micro_cents' => 0,
                        'tokens' => TikToken::countTokens($chunk),
                        'embedding' => $vectors[$chunkChecksum],
                        'embedding_bm25' => Bm25Tokenizer::expression($chunk),
                        'metadata' => [],
                    ]
                );
            }

            $chunkModels[$chunkModel->id] = $chunkModel;

            if (($idx + 1) % self::PROGRESS_EVENT_EVERY === 0) {
                WebsiteIngestionProgressed::dispatch($website, $agent->external_id);
            }
        }

        // Link every chunk to this URL in one statement. The primary key
        // (chunk_id, referenceable_type, referenceable_id) makes a repeat a no-op.
        ChunkReference::query()->insertOrIgnore(array_map(
            static fn (int $chunkId): array => [
                'chunk_id' => $chunkId,
                'referenceable_type' => $websiteUrl->getMorphClass(),
                'referenceable_id' => $websiteUrl->id,
            ],
            array_keys($chunkModels),
        ));

        if ($enhance) {
            foreach ($chunkModels as $chunkModel) {
                WebsiteUrlEnhanceJob::dispatch($agent, $websiteUrl, $chunkModel);
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
            'status' => SourceStatus::Completed->value,
            'checksum' => $checksum,
            'crawled_at' => now(),
        ]);

        $website->refresh();

        UrlIngestionStarted::dispatch($websiteUrl, $agent->external_id);
        WebsiteIngestionProgressed::dispatch($website, $agent->external_id);
    }
}
