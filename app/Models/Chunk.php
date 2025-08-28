<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;
use Pgvector\Laravel\Vector;

class Chunk extends Model
{
    protected $guarded = ['id'];

    public function casts(): array
    {
        return [
            'embedding' => Vector::class,
            'keywords' => 'json',
            'metadata' => 'json',
        ];
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function chunkable(): MorphTo
    {
        return $this->morphTo('chunkable');
    }

    public function images(): Attribute
    {
        return Attribute::get(function () {
            return collect($this->metadata['images'] ?? [])
                ->keys()
                ->map(fn ($image) => Storage::url($image))
                ->toArray();
        });
    }
}
