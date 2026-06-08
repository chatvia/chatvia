<?php

namespace Domain\Knowledge\Ingestion\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
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
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChunkReference whereReferenceableId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChunkReference whereReferenceableType($value)
 *
 * @mixin \Eloquent
 */
#[Guarded(['id'])]
#[WithoutTimestamps]
class ChunkReference extends Model
{
    // The table has a composite primary key (chunk_id, referenceable_type,
    // referenceable_id) and no auto-incrementing `id` column. Without this,
    // Eloquent inserts with `returning "id"` and Postgres errors with
    // "column id does not exist". Uniqueness/dedup is enforced by the composite
    // PK, which firstOrCreate()/createOrFirst() relies on to find the existing
    // row after a duplicate insert.
    public $incrementing = false;

    public function chunk(): BelongsTo
    {
        return $this->belongsTo(Chunk::class);
    }

    public function referenceable(): MorphTo
    {
        return $this->morphTo();
    }
}
