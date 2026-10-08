<?php

namespace Domain\Knowledge\Ingestion\Models;

use Domain\Knowledge\Ingestion\QueryBuilders\ChunkQueryBuilder;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Pgvector\Laravel\HalfVector;
use Support\Concerns\HasExternalId;

/**
 * @property int $id
 * @property string|null $external_id
 * @property int $user_id
 * @property string $chunkable_type
 * @property int $chunkable_id
 * @property int $chunk_index
 * @property string $chunk
 * @property bool $is_enhanced
 * @property string|null $chunked_using_model
 * @property string|null $summary
 * @property string|null $questions
 * @property HalfVector|null $questions_embedding
 * @property string|null $questions_embedding_bm25
 * @property int $cost_in_micro_cents
 * @property int $tokens
 * @property HalfVector $embedding
 * @property string $embedding_bm25
 * @property array<array-key, mixed>|null $metadata
 * @property string $checksum
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Model|\Eloquent $chunkable
 * @property-read Collection<int, ChunkReference> $references
 * @property-read int|null $references_count
 *
 * @method static ChunkQueryBuilder<static>|Chunk configureSearchAccuracy(string $accuracyLevel, array $config)
 * @method static ChunkQueryBuilder<static>|Chunk hybridSearchForAgent(int $agentId, int $workspaceId, string $query, string $vectorString, int $limit = 4)
 * @method static ChunkQueryBuilder<static>|Chunk newModelQuery()
 * @method static ChunkQueryBuilder<static>|Chunk newQuery()
 * @method static ChunkQueryBuilder<static>|Chunk query()
 * @method static ChunkQueryBuilder<static>|Chunk whereChecksum($value)
 * @method static ChunkQueryBuilder<static>|Chunk whereChunk($value)
 * @method static ChunkQueryBuilder<static>|Chunk whereChunkIndex($value)
 * @method static ChunkQueryBuilder<static>|Chunk whereChunkableId($value)
 * @method static ChunkQueryBuilder<static>|Chunk whereChunkableType($value)
 * @method static ChunkQueryBuilder<static>|Chunk whereChunkedUsingModel($value)
 * @method static ChunkQueryBuilder<static>|Chunk whereCostInCents($value)
 * @method static ChunkQueryBuilder<static>|Chunk whereCreatedAt($value)
 * @method static ChunkQueryBuilder<static>|Chunk whereEmbedding($value)
 * @method static ChunkQueryBuilder<static>|Chunk whereEmbeddingBm25($value)
 * @method static ChunkQueryBuilder<static>|Chunk whereEmbeddingModel($value)
 * @method static ChunkQueryBuilder<static>|Chunk whereIsEnhanced($value)
 * @method static ChunkQueryBuilder<static>|Chunk whereExternalId($value)
 * @method static ChunkQueryBuilder<static>|Chunk whereId($value)
 * @method static ChunkQueryBuilder<static>|Chunk whereMetadata($value)
 * @method static ChunkQueryBuilder<static>|Chunk whereSummary($value)
 * @method static ChunkQueryBuilder<static>|Chunk whereTokens($value)
 * @method static ChunkQueryBuilder<static>|Chunk whereType($value)
 * @method static ChunkQueryBuilder<static>|Chunk whereUpdatedAt($value)
 * @method static ChunkQueryBuilder<static>|Chunk whereUserId($value)
 *
 * @mixin \Eloquent
 */
#[Guarded(['id'])]
class Chunk extends Model
{
    use HasExternalId;

    public function newEloquentBuilder($query): ChunkQueryBuilder
    {
        return new ChunkQueryBuilder($query);
    }

    protected function casts(): array
    {
        return [
            // chunk/summary/questions are plaintext text columns. The searchable
            // embedding columns are plaintext too (vector/BM25 search needs them).
            'embedding' => HalfVector::class,
            'metadata' => 'json',
        ];
    }

    public function chunkable(): MorphTo
    {
        return $this->morphTo('chunkable');
    }

    public function references(): HasMany
    {
        return $this->hasMany(ChunkReference::class);
    }
}
