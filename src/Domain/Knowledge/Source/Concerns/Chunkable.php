<?php

namespace Domain\Knowledge\Source\Concerns;

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Ingestion\Models\Chunk;
use Domain\Knowledge\Source\Enums\SourceStatus;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Facades\Storage;

/**
 * Chunk relations and ingestion progress for a knowledge source model. Chunks
 * are keyed by the source's morph alias (`chunkable_type`), so they belong to
 * the source, not to an agent, and are shared by every agent that uses it.
 *
 * The summary chunk has `chunk_index` 0 and is kept out of `chunks()`; reach it
 * through `summary()`.
 */
trait Chunkable
{
    /**
     * Auto-registered by Eloquent for any model using this trait. Cascades chunk
     * deletion via the polymorphic key so the summary chunk (chunk_index=0) is
     * caught too — the chunks() relation below scopes it out, so going through
     * the relation would orphan it. FK cascade handles chunk_references cleanup.
     */
    public static function bootChunkable(): void
    {
        static::deleting(function ($model): void {
            Chunk::query()
                ->where('chunkable_type', $model->getMorphClass())
                ->where('chunkable_id', $model->getKey())
                ->delete();
        });
    }

    /**
     * @return MorphMany<Chunk, $this>
     */
    public function chunks(): MorphMany
    {
        return $this->morphMany(Chunk::class, 'chunkable')->whereNot('chunk_index', 0);
    }

    /**
     * Agents this source is attached to via the polymorphic agent_sources pivot.
     *
     * @return MorphToMany<Agent, $this>
     */
    public function agents(): MorphToMany
    {
        return $this->morphToMany(Agent::class, 'sourceable', 'agent_sources');
    }

    public function ingestionProgress(): int
    {
        if ($this->status === SourceStatus::Completed) {
            return 100;
        }

        if ($this->status !== SourceStatus::Processing) {
            return 0;
        }

        $totalChunks = $this->expected_chunks ?? 0;

        // Prefer `withCount('chunks')` from the caller (avoids N COUNT
        // queries on list pages); fall back to a per-row count if absent.
        $importedChunks = $this->chunks_count ?? $this->chunks()->count();

        $progress = (int) round($totalChunks > 0 ? ($importedChunks / $totalChunks) * 100 : 0);

        // While still Processing, never report a full 100%. Completion is
        // signalled solely by the status flip to Completed (short-circuited
        // above). If imported >= expected before the batch `finally()` commits
        // the status, a 100 here makes the UI render "ingested" and then revert
        // to "Training in progress" on the next read that still sees Processing.
        // Cap at 99 so 100% unambiguously means "done".
        return min($progress, 99);
    }

    public function summary(): MorphOne
    {
        return $this
            ->morphOne(Chunk::class, 'chunkable')
            ->where('chunk_index', 0);
    }

    /**
     * Image URLs pulled out of `metadata->images` keys across this source's
     * chunks. Filtered + projected in SQL so we only ship rows that actually
     * have images, instead of loading every chunk's metadata into PHP.
     *
     * @return array<int, string>
     */
    public function images(): array
    {
        // @> containment hits the GIN index on chunks.metadata; the previous
        // `jsonb_typeof(metadata->'images')` shape was unindexable.
        return $this->chunks()
            ->where('metadata', '@>', '{"images": {}}')
            ->selectRaw("metadata->'images' AS images")
            ->pluck('images')
            ->flatMap(fn ($json) => collect(is_string($json) ? json_decode($json, true) : (array) $json)->keys())
            ->unique()
            ->map(fn (string $path): string => Storage::disk('public')->url($path))
            ->values()
            ->toArray();
    }
}
