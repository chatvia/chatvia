<?php

namespace Domain\Knowledge\Ingestion\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Attributes\WithoutIncrementing;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Links a chunk to a website page (AgentWebsiteUrl) that produced it. Website
 * chunks are shared by checksum across pages, so one chunk can have several
 * references; a page can be retrained or removed without touching chunks other
 * pages still use. `referenceable_type` holds the morph alias.
 *
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
#[WithoutIncrementing]
class ChunkReference extends Model
{
    public function chunk(): BelongsTo
    {
        return $this->belongsTo(Chunk::class);
    }

    public function referenceable(): MorphTo
    {
        return $this->morphTo();
    }
}
