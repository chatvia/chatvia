<?php

namespace Domain\Ingestion\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Pgvector\Laravel\Vector;

/**
 * @property int $id
 * @property int $chunk_id
 * @property string $question
 * @property Vector $embedding
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Chunk $chunk
 */
#[Guarded(['id'])]
class ChunkQuestion extends Model
{
    public function casts(): array
    {
        return [
            'embedding' => Vector::class,
        ];
    }

    public function chunk(): BelongsTo
    {
        return $this->belongsTo(Chunk::class);
    }
}
