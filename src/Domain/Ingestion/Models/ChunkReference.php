<?php

namespace Domain\Ingestion\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ChunkReference extends Model
{
    protected $guarded = ['id'];

    public function chunk(): BelongsTo
    {
        return $this->belongsTo(Chunk::class);
    }

    public function referenceable(): MorphTo
    {
        return $this->morphTo();
    }
}
