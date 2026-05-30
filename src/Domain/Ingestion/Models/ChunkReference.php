<?php

namespace Domain\Ingestion\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property int $chunk_id
 * @property string $referenceable_type
 * @property int $referenceable_id
 * @property-read Chunk $chunk
 * @property-read Model|\Eloquent $referenceable
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChunkReference newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChunkReference newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChunkReference query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChunkReference whereChunkId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChunkReference whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChunkReference whereReferenceableId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChunkReference whereReferenceableType($value)
 *
 * @mixin \Eloquent
 */
#[Guarded(['id'])]
class ChunkReference extends Model
{
    public $timestamps = false;

    public function chunk(): BelongsTo
    {
        return $this->belongsTo(Chunk::class);
    }

    public function referenceable(): MorphTo
    {
        return $this->morphTo();
    }
}
