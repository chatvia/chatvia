<?php

namespace Support\Traits;

use Domain\Agents\Models\Agent;
use Domain\Ingestion\Models\Chunk;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Facades\Storage;

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
     * @return BelongsToMany<Agent, $this>
     */
    public function agents(): BelongsToMany
    {
        return $this->belongsToMany(Agent::class, 'agent_chunk');
    }

    public function tempUrl(): string
    {
        return Storage::temporaryUrl($this->filepath, now()->addMinutes(60));
    }

    public function ingestionProgress(): int
    {
        if ($this->status === 'ingested') {
            return 100;
        }

        if ($this->status !== 'ingesting') {
            return 0;
        }

        $totalChunks = $this->expected_chunks ?? 0;

        $importedChunks = $this->chunks()->count();

        return (int) round($totalChunks > 0 ? ($importedChunks / $totalChunks) * 100 : 0);
    }

    public function summary(): MorphOne
    {
        return $this
            ->morphOne(Chunk::class, 'chunkable')
            ->where('chunk_index', 0);
    }

    public function images()
    {
        return $this->chunks()
            ->select('metadata')
            ->get()
            ->reject(fn ($item): bool => count($item['metadata']['images'] ?? []) === 0)
            ->pluck('metadata.images')
            ->map(fn ($images) => collect($images)->keys()->map(fn ($image) => Storage::disk('public')->url($image))->toArray())
            ->flatten()
            ->toArray();
    }
}
