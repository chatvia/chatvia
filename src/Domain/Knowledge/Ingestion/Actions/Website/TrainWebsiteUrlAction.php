<?php

namespace Domain\Knowledge\Ingestion\Actions\Website;

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Ingestion\Actions\Chunks\CreateSourceChunksAction;
use Domain\Knowledge\Ingestion\Contracts\TextSplitter;
use Domain\Knowledge\Ingestion\Data\Chunking\ChunkingConfigData;
use Domain\Knowledge\Ingestion\Data\Chunking\ChunkPagesData;
use Domain\Knowledge\Ingestion\Data\Chunking\PageGroupData;
use Domain\Knowledge\Ingestion\Data\Ocr\PageData;
use Domain\Knowledge\Ingestion\Enums\ChunkEnhancement;
use Domain\Knowledge\Ingestion\Enums\ContentKind;
use Domain\Knowledge\Ingestion\Models\Chunk;
use Domain\Knowledge\Ingestion\Models\ChunkReference;
use Domain\Knowledge\Source\Enums\SourceStatus;
use Domain\Knowledge\Source\Web\Events\UrlIngestionStarted;
use Domain\Knowledge\Source\Web\Events\WebsiteIngestionProgressed;
use Domain\Knowledge\Source\Web\Models\AgentWebsiteUrl;
use Domain\Knowledge\Source\Web\Models\SourceWebsite;
use Domain\Knowledge\Source\Web\Services\Firecrawl;
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
 * checksum as last time: the same content split with the same chunk settings)
 * costs no embedding, and only parts the website does not hold yet are
 * enhanced and embedded (see {@see CreateSourceChunksAction}).
 * When a page changed, its links to parts it no longer has are dropped, and
 * those chunks are deleted once no page uses them.
 */
class TrainWebsiteUrlAction
{
    public function __construct(
        private readonly Firecrawl $firecrawl,
        private readonly TextSplitter $splitter,
        private readonly CreateSourceChunksAction $createChunks,
    ) {}

    /**
     * @param  int|null  $progress  the website's progress (0-100) once this page is done, from the job batch
     */
    public function execute(AgentWebsiteUrl $websiteUrl, Agent $agent, ?int $progress = null): void
    {
        // FireCrawl fetches the page from its own servers, so there is no SSRF
        // surface here; obviously non-public targets are still refused before
        // a scrape credit is spent on them.
        SafeUrl::assert($websiteUrl->url);

        $scraped = $this->firecrawl->scrape($websiteUrl->url);
        $markdown = trim($scraped->markdown);
        $title = $scraped->title();

        /** @var SourceWebsite $website */
        $website = $websiteUrl->agentWebsite;

        // Nothing scrapable (empty page, blocked, JS-only with no content).
        if ($markdown === '') {
            $this->finish($websiteUrl, $website, $agent, $progress, ['title' => $title, 'crawled_at' => now()]);

            return;
        }

        // The content and how it is split, so new chunk settings (Settings →
        // Chunking) train an unchanged page again.
        $checksum = hash('sha256', ChunkingConfigData::forKind(ContentKind::Markdown)->fingerprint().$markdown);

        if ($websiteUrl->checksum === $checksum) {
            $this->finish($websiteUrl, $website, $agent, $progress, ['title' => $title]);

            return;
        }

        /** @var list<string> $parts */
        $parts = Cache::remember(
            'ingestion:website:chunks:'.$websiteUrl->id.':'.$checksum,
            now()->addDay(),
            fn (): array => $this->splitter->split($markdown, ContentKind::Markdown),
        );

        $pages = PageData::fromParts($parts);

        $this->createChunks->execute(new ChunkPagesData(
            source: $website,
            group: new PageGroupData($pages),
            enhancement: ChunkEnhancement::for($website),
        ));

        $this->linkChunks($websiteUrl, $website, array_map(static fn (PageData $page): string => $page->checksum, $pages));

        $this->addExpectedChunks($website, count($parts));

        $this->finish($websiteUrl, $website, $agent, $progress, ['title' => $title, 'checksum' => $checksum, 'crawled_at' => now()]);
    }

    /**
     * Link this page to the chunks of its parts and unlink it from the parts
     * it no longer has, in one transaction. The primary key (chunk_id,
     * referenceable_type, referenceable_id) makes a repeated link a no-op.
     *
     * @param  list<string>  $checksums
     */
    private function linkChunks(AgentWebsiteUrl $websiteUrl, SourceWebsite $website, array $checksums): void
    {
        $chunkIds = Chunk::query()->ofSource($website)->whereIn('checksum', $checksums)->pluck('id')->all();
        $morphType = $websiteUrl->getMorphClass();

        DB::transaction(function () use ($websiteUrl, $chunkIds, $morphType): void {
            ChunkReference::query()->insertOrIgnore(array_map(
                static fn (int $chunkId): array => [
                    'chunk_id' => $chunkId,
                    'referenceable_type' => $morphType,
                    'referenceable_id' => $websiteUrl->id,
                ],
                $chunkIds,
            ));

            $stale = ChunkReference::query()
                ->where('referenceable_type', $morphType)
                ->where('referenceable_id', $websiteUrl->id)
                ->whereNotIn('chunk_id', $chunkIds);

            $staleChunkIds = (clone $stale)->pluck('chunk_id')->all();

            if ($staleChunkIds === []) {
                return;
            }

            $stale->delete();

            // Chunks are shared by checksum across a website's pages; keep any
            // chunk another page still points at.
            Chunk::query()->whereIn('id', $staleChunkIds)->unreferenced()->delete();
        });
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
     * Mark the page as trained, tell its URL list, and send the website's
     * progress.
     *
     * @param  array<string, mixed>  $attributes  extra columns to save with the status
     */
    private function finish(AgentWebsiteUrl $websiteUrl, SourceWebsite $website, Agent $agent, ?int $progress, array $attributes = []): void
    {
        $websiteUrl->update(['status' => SourceStatus::Completed, ...$attributes]);

        UrlIngestionStarted::dispatch($websiteUrl, $agent->external_id);

        if ($progress !== null) {
            WebsiteIngestionProgressed::dispatch($website, $agent->external_id, $progress);
        }
    }
}
