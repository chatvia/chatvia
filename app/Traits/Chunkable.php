<?php

namespace App\Traits;

use App\Models\Agent;
use App\Models\Chunk;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Facades\Storage;

trait Chunkable
{
    public function chunks(): MorphMany
    {
        return $this->morphMany(Chunk::class, 'chunkable');
    }

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

        return round($totalChunks > 0 ? ($importedChunks / $totalChunks) * 100 : 0);
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
            ->reject(fn ($item) => count($item['metadata']['images'] ?? []) === 0)
            ->pluck('metadata.images')
            ->map(fn ($images) => collect($images)->keys()->map(fn ($image) => Storage::disk('public')->url($image))->toArray())
            ->flatten()
            ->toArray();
    }
}
