<?php

namespace Domain\Knowledge\Ingestion\Actions\Website;

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Embedding\Contracts\EmbeddingsProviderInterface;
use Domain\Knowledge\Ingestion\Contracts\TextSplitter;
use Domain\Knowledge\Ingestion\Jobs\Website\WebsiteUrlEnhanceJob;
use Domain\Knowledge\Ingestion\Models\Chunk;
use Domain\Knowledge\Ingestion\Models\ChunkReference;
use Domain\Knowledge\Retrieval\Services\Bm25Tokenizer;
use Domain\Knowledge\Source\Enums\SourceStatus;
use Domain\Knowledge\Source\Web\Events\UrlIngestionStarted;
use Domain\Knowledge\Source\Web\Events\WebsiteIngestionProgressed;
use Domain\Knowledge\Source\Web\Models\AgentWebsiteUrl;
use Domain\Knowledge\Source\Web\Models\SourceWebsite;
use Domain\Knowledge\Source\Web\Services\Firecrawl;
use Domain\Llm\Services\TikToken;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Support\Http\SafeUrl;

/**
 * Trains one page of a website: scrape it with FireCrawl, split the Markdown,
 * and store the parts as chunks of the website.
 *
 * Chunks belong to the website and are keyed by content checksum, so a part
 * that several pages share is stored and embedded once; `chunk_references`
 * link each chunk to every page that produced it. An unchanged page (same
 * checksum as last time) costs no embedding, and only parts the website does
 * not hold yet are embedded, in one batched request.
 */
class TrainWebsiteUrlAction
{
    /** Send a progress event after every this many chunks. */
    private const int PROGRESS_EVENT_EVERY = 25;

    public function __construct(
        private readonly Firecrawl $firecrawl,
        private readonly TextSplitter $splitter,
        private readonly EmbeddingsProviderInterface $embeddings,
    ) {}

    public function execute(AgentWebsiteUrl $websiteUrl, Agent $agent): void
    {
        // FireCrawl fetches the page from its own servers, so there is no SSRF
        // surface here; obviously non-public targets are still refused before
        // a scrape credit is spent on them.
        SafeUrl::assert($websiteUrl->url);

        $markdown = trim($this->firecrawl->scrape($websiteUrl->url)->markdown);

        // Nothing scrapable (empty page, blocked, JS-only with no content).
        if ($markdown === '') {
            $this->finish($websiteUrl, $agent, ['crawled_at' => now()]);

            return;
        }

        $checksum = hash('sha256', $markdown);

        if ($websiteUrl->checksum === $checksum) {
            $this->finish($websiteUrl, $agent);

            return;
        }

        $parts = Cache::remember(
            'ingestion:website:chunks:'.$websiteUrl->id.':'.$checksum,
            now()->addDay(),
            fn (): array => $this->splitter->split($markdown),
        );

        /** @var SourceWebsite $website */
        $website = $websiteUrl->agentWebsite;

        $chunks = $this->storeChunks($website, $agent, $parts);

        $this->linkChunks($websiteUrl, $chunks);

        if (config('chatvia.ai.chunker.enhance') === true) {
            $chunks->each(fn (Chunk $chunk) => WebsiteUrlEnhanceJob::dispatch($agent, $websiteUrl, $chunk));
        }

        $this->addExpectedChunks($website, count($parts));

        $this->finish($websiteUrl, $agent, ['checksum' => $checksum, 'crawled_at' => now()]);

        WebsiteIngestionProgressed::dispatch($website->refresh(), $agent->external_id);
    }

    /**
     * Create the parts the website does not hold yet and renumber the ones it
     * already has, so `chunk_index` follows this page's order.
     *
     * @param  list<string>  $parts
     * @return Collection<int, Chunk> keyed by chunk id
     */
    private function storeChunks(SourceWebsite $website, Agent $agent, array $parts): Collection
    {
        $checksums = array_map(static fn (string $part): string => hash('sha256', $part), $parts);

        $existing = Chunk::query()
            ->where('chunkable_type', $website->getMorphClass())
            ->where('chunkable_id', $website->id)
            ->whereIn('checksum', $checksums)
            ->get(['id', 'checksum', 'chunk_index'])
            ->keyBy('checksum');

        $vectors = $this->embedMissing($parts, $checksums, $existing);
        $chunks = collect();

        foreach ($parts as $index => $part) {
            $chunkIndex = $index + 1;

            /** @var Chunk|null $chunk */
            $chunk = $existing->get($checksums[$index]);

            if ($chunk !== null && $chunk->chunk_index !== $chunkIndex) {
                $chunk->update(['chunk_index' => $chunkIndex]);
            }

            $chunk ??= $this->createChunk($website, $agent, $part, $checksums[$index], $chunkIndex, $vectors[$checksums[$index]]);

            $chunks->put($chunk->id, $chunk);

            if ($chunkIndex % self::PROGRESS_EVENT_EVERY === 0) {
                WebsiteIngestionProgressed::dispatch($website, $agent->external_id);
            }
        }

        return $chunks;
    }

    /**
     * Embed, in one request, the parts that have no chunk yet.
     *
     * @param  list<string>  $parts
     * @param  list<string>  $checksums
     * @param  Collection<string, Chunk>  $existing
     * @return array<string, string> checksum => vector literal
     */
    private function embedMissing(array $parts, array $checksums, Collection $existing): array
    {
        $missing = array_values(array_filter(
            $parts,
            fn (int $index): bool => ! $existing->has($checksums[$index]),
            ARRAY_FILTER_USE_KEY,
        ));

        $vectors = [];

        foreach ($this->embeddings->embedBatch($missing) as $i => $vector) {
            $vectors[hash('sha256', $missing[$i])] = '['.implode(',', $vector).']';
        }

        return $vectors;
    }

    private function createChunk(SourceWebsite $website, Agent $agent, string $part, string $checksum, int $chunkIndex, string $vector): Chunk
    {
        // createOrFirst: two pages of the same site can produce the same part
        // at the same time.
        return Chunk::query()->createOrFirst(
            [
                'chunkable_type' => $website->getMorphClass(),
                'chunkable_id' => $website->id,
                'checksum' => $checksum,
            ],
            [
                'user_id' => $agent->user_id,
                'chunk_index' => $chunkIndex,
                'chunk' => $part,
                'summary' => null,
                'chunked_using_model' => $this->splitter->name(),
                'cost_in_micro_cents' => 0,
                'tokens' => TikToken::countTokens($part),
                'embedding' => $vector,
                'embedding_bm25' => Bm25Tokenizer::expression($part),
                'metadata' => [],
            ],
        );
    }

    /**
     * Link every chunk to this page in one statement. The primary key
     * (chunk_id, referenceable_type, referenceable_id) makes a repeat a no-op.
     *
     * @param  Collection<int, Chunk>  $chunks  keyed by chunk id
     */
    private function linkChunks(AgentWebsiteUrl $websiteUrl, Collection $chunks): void
    {
        ChunkReference::query()->insertOrIgnore(array_map(
            static fn (int $chunkId): array => [
                'chunk_id' => $chunkId,
                'referenceable_type' => $websiteUrl->getMorphClass(),
                'referenceable_id' => $websiteUrl->id,
            ],
            $chunks->keys()->all(),
        ));
    }

    /**
     * Pages are trained in parallel, so the website row is locked while its
     * expected chunk count grows.
     */
    private function addExpectedChunks(SourceWebsite $website, int $count): void
    {
        DB::transaction(function () use ($website, $count): void {
            SourceWebsite::query()
                ->whereKey($website->id)
                ->lockForUpdate()
                ->first()
                ?->increment('expected_chunks', $count);
        });
    }

    /**
     * Mark the page as trained and tell its URL list.
     *
     * @param  array<string, mixed>  $attributes  extra columns to save with the status
     */
    private function finish(AgentWebsiteUrl $websiteUrl, Agent $agent, array $attributes = []): void
    {
        $websiteUrl->update(['status' => SourceStatus::Completed, ...$attributes]);

        UrlIngestionStarted::dispatch($websiteUrl, $agent->external_id);
    }
}
