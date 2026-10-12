<?php

namespace Domain\Knowledge\Source\Concerns;

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Ingestion\Models\Chunk;
use Domain\Knowledge\Source\Enums\SourceStatus;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Collection;
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
        static::deleting(fn (self $model) => $model->deleteChunks());
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

    /**
     * The source's ingestion progress (0-100) for a list page. While it is
     * processing, the stored chunks are compared with the expected count; the
     * caller loads that count with `loadCount('chunks')` for the rows that
     * are processing (the live progress comes from the job batch instead).
     */
    public function ingestionProgress(): int
    {
        if ($this->status === SourceStatus::Completed) {
            return 100;
        }

        if ($this->status !== SourceStatus::Processing) {
            return 0;
        }

        $totalChunks = $this->expected_chunks ?? 0;
        $importedChunks = $this->chunks_count ?? 0;

        $progress = (int) round($totalChunks > 0 ? ($importedChunks / $totalChunks) * 100 : 0);

        // While still Processing, never report a full 100%. Completion is
        // signalled solely by the status flip to Completed (short-circuited
        // above). If imported >= expected before the batch `finally()` commits
        // the status, a 100 here makes the UI render "ingested" and then revert
        // to "Training in progress" on the next read that still sees Processing.
        // Cap at 99 so 100% unambiguously means "done".
        return min($progress, 99);
    }

    /** Deletes every chunk of this source, the summary chunk (index 0) included. */
    public function deleteChunks(): void
    {
        Chunk::query()->ofSource($this)->delete();
    }

    /** @return MorphOne<Chunk, $this> */
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
        // Only this source's chunks are scanned (chunks_page_idx), so the
        // containment test needs no index of its own.
        return $this->chunks()
            ->where('metadata', '@>', '{"images": {}}')
            ->selectRaw("metadata->'images' AS images")
            ->pluck('images')
            ->flatMap(function (mixed $json): Collection {
                // The containment filter above guarantees `images` is a JSON object.
                /** @var array<string, mixed> $images */
                $images = is_string($json) ? json_decode($json, true) : (array) $json;

                return collect($images)->keys();
            })
            ->unique()
            ->map(fn (string $path): string => Storage::disk('public')->url($path))
            ->values()
            ->toArray();
    }
}
