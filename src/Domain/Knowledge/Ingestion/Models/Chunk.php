<?php

namespace Domain\Knowledge\Ingestion\Models;

use Database\Factories\ChunkFactory;
use Domain\Knowledge\Ingestion\QueryBuilders\ChunkQueryBuilder;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Pgvector\Laravel\HalfVector;
use Support\Concerns\HasExternalId;

/**
 * One searchable piece of a knowledge source: the text, its dense embedding
 * (pgvector, the install's type and size) and lexical search text, and — once
 * enhanced — a summary and the questions it answers with their own embeddings.
 *
 * Chunks belong to a source, not to an agent: `chunkable_type` holds the
 * source's morph alias (never a class name), and agents reach chunks through
 * `agent_sources`. Chunk index 0 is the source's summary chunk.
 *
 * @property int $id
 * @property string $external_id
 * @property int $workspace_id
 * @property string $chunkable_type
 * @property int $chunkable_id
 * @property int $chunk_index
 * @property string $chunk
 * @property bool $is_enhanced
 * @property string|null $chunked_using_model
 * @property string|null $summary
 * @property string|null $questions
 * @property HalfVector|null $questions_embedding
 * @property int $tokens
 * @property HalfVector $embedding
 * @property string $search_text
 * @property array<array-key, mixed>|null $metadata
 * @property string $checksum
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Model|\Eloquent $chunkable
 * @property-read Collection<int, ChunkReference> $references
 * @property-read int|null $references_count
 *
 * @method static ChunkQueryBuilder<static>|Chunk ofSource(Model $source)
 * @method static ChunkQueryBuilder<static>|Chunk unreferenced()
 * @method static ChunkQueryBuilder<static>|Chunk newModelQuery()
 * @method static ChunkQueryBuilder<static>|Chunk newQuery()
 * @method static ChunkQueryBuilder<static>|Chunk query()
 * @method static ChunkQueryBuilder<static>|Chunk whereChecksum($value)
 * @method static ChunkQueryBuilder<static>|Chunk whereChunk($value)
 * @method static ChunkQueryBuilder<static>|Chunk whereChunkIndex($value)
 * @method static ChunkQueryBuilder<static>|Chunk whereChunkableId($value)
 * @method static ChunkQueryBuilder<static>|Chunk whereChunkableType($value)
 * @method static ChunkQueryBuilder<static>|Chunk whereChunkedUsingModel($value)
 * @method static ChunkQueryBuilder<static>|Chunk whereCreatedAt($value)
 * @method static ChunkQueryBuilder<static>|Chunk whereEmbedding($value)
 * @method static ChunkQueryBuilder<static>|Chunk whereEmbeddingModel($value)
 * @method static ChunkQueryBuilder<static>|Chunk whereSearchText($value)
 * @method static ChunkQueryBuilder<static>|Chunk whereIsEnhanced($value)
 * @method static ChunkQueryBuilder<static>|Chunk whereExternalId($value)
 * @method static ChunkQueryBuilder<static>|Chunk whereId($value)
 * @method static ChunkQueryBuilder<static>|Chunk whereMetadata($value)
 * @method static ChunkQueryBuilder<static>|Chunk whereSummary($value)
 * @method static ChunkQueryBuilder<static>|Chunk whereTokens($value)
 * @method static ChunkQueryBuilder<static>|Chunk whereType($value)
 * @method static ChunkQueryBuilder<static>|Chunk whereUpdatedAt($value)
 * @method static ChunkQueryBuilder<static>|Chunk whereWorkspaceId($value)
 *
 * @mixin \Eloquent
 */
#[Guarded(['id'])]
#[UseFactory(ChunkFactory::class)]
class Chunk extends Model
{
    /** @use HasFactory<ChunkFactory> */
    use HasExternalId, HasFactory;

    /** @return ChunkQueryBuilder<self> */
    public function newEloquentBuilder($query): ChunkQueryBuilder
    {
        return new ChunkQueryBuilder($query);
    }

    protected function casts(): array
    {
        return [
            // chunk/summary/questions/search_text are plaintext text columns.
            'embedding' => HalfVector::class,
            'is_enhanced' => 'boolean',
            'metadata' => 'json',
        ];
    }

    /** @return MorphTo<Model, $this> */
    public function chunkable(): MorphTo
    {
        return $this->morphTo('chunkable');
    }

    /** @return HasMany<ChunkReference, $this> */
    public function references(): HasMany
    {
        return $this->hasMany(ChunkReference::class);
    }
}
