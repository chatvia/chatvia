<?php

namespace App\Traits;

use App\Models\Chunk;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Storage;

trait Chunkable
{
    public function chunks(): MorphMany
    {
        return $this->morphMany(Chunk::class, 'chunkable');
    }

    public function tempUrl(): string
    {
        return Storage::temporaryUrl($this->filepath, now()->addMinutes(60));
    }

    public function ingestionProgress(): int
    {
        $totalChunks = $this->expected_chunks ?? 0;
        $importedChunks = $this->chunks()->count();

        return round($totalChunks > 0 ? ($importedChunks / $totalChunks) * 100 : 0);
    }

    public function summary(): HasOne
    {
        return $this->hasOne(Chunk::class, 'chunkable_id')
            ->where('chunkable_type', static::class)
            ->where('chunk_index', 0)
            ->latestOfMany();
    }

    public function images()
    {
        return $this->chunks()
            ->select('metadata')
            ->get()
            ->reject(fn ($item) => count($item['metadata']['images'] ?? []) === 0)
            ->pluck('metadata.images')
            ->map(fn ($images) => collect($images)->keys()->map(fn ($image) => Storage::url($image))->toArray())
            ->flatten()
            ->toArray();
    }
}
