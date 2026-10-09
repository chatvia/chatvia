<?php

namespace Domain\Knowledge\Source\Web\Actions\Url;

use Domain\Knowledge\Ingestion\Models\Chunk;
use Domain\Knowledge\Ingestion\Models\ChunkReference;
use Domain\Knowledge\Source\Web\Models\AgentWebsiteUrl;
use Illuminate\Support\Facades\DB;

/**
 * Removes one crawled URL from a website source together with the chunks
 * that only this URL produced. Chunks are shared by checksum across a website's
 * pages, so a chunk another URL still points at is kept.
 */
class DeleteAgentWebsiteUrlAction
{
    public function execute(AgentWebsiteUrl $link): void
    {
        DB::transaction(function () use ($link): void {
            $chunkIds = $link->chunks()->pluck('chunks.id');

            ChunkReference::query()
                ->whereIn('referenceable_type', AgentWebsiteUrl::referenceTypes())
                ->where('referenceable_id', $link->id)
                ->delete();

            // Chunks are shared by checksum across a website's pages; keep any
            // chunk another URL still points at.
            Chunk::query()
                ->whereIn('id', $chunkIds)
                ->whereDoesntHave('references')
                ->delete();

            $link->delete();
        });
    }
}
