<?php

namespace Domain\Knowledge\Source\Web\Actions\Url;

use Domain\Knowledge\Ingestion\Models\Chunk;
use Domain\Knowledge\Ingestion\Models\ChunkReference;
use Domain\Knowledge\Source\Web\Models\AgentWebsiteUrl;
use Domain\Knowledge\Source\Web\Models\SourceWebsite;
use Illuminate\Support\Facades\DB;

/**
 * Removes several pages from a website in one go, with the chunks only they
 * produced (the same rule as {@see DeleteAgentWebsiteUrlAction}). The work is
 * set-based: one statement per step for all the pages, not one round per
 * page. Ids of URLs that belong to another website are ignored.
 */
class DeleteAgentWebsiteUrlsAction
{
    /**
     * @param  list<int>  $urlIds
     * @return int how many URLs were removed
     */
    public function execute(SourceWebsite $website, array $urlIds): int
    {
        return DB::transaction(function () use ($website, $urlIds): int {
            $ids = $website->urls()->whereIn('id', $urlIds)->pluck('id');

            if ($ids->isEmpty()) {
                return 0;
            }

            $references = ChunkReference::query()
                ->where('referenceable_type', new AgentWebsiteUrl()->getMorphClass())
                ->whereIn('referenceable_id', $ids);

            $chunkIds = (clone $references)->distinct()->pluck('chunk_id');

            $references->delete();

            // Chunks are shared by checksum across a website's pages; keep any
            // chunk a page that stays still points at. The ids are integers,
            // inlined so a long list never runs into the bound-parameter cap.
            if ($chunkIds->isNotEmpty()) {
                Chunk::query()->whereIntegerInRaw('id', $chunkIds->all())->unreferenced()->delete();
            }

            AgentWebsiteUrl::query()->whereIn('id', $ids)->delete();

            return $ids->count();
        });
    }
}
