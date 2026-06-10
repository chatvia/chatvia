<?php

namespace Domain\Knowledge\Ingestion\Models;

use Domain\Knowledge\Ingestion\Data\OCR\PageData;
use Domain\Knowledge\Ingestion\QueryBuilders\ChunkQueryBuilder;
use Domain\Knowledge\Source\Web\Models\AgentWebsiteUrl;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Pgvector\Laravel\Vector;
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
 * @property string|null $embedding_model
 * @property string|null $summary
 * @property string|null $questions
 * @property Vector|null $questions_embedding
 * @property string|null $questions_embedding_bm25
 * @property int $cost_in_micro_cents
 * @property int $tokens
 * @property Vector $embedding
 * @property string $embedding_bm25
 * @property array<array-key, mixed>|null $metadata
 * @property string $checksum
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Model|\Eloquent $chunkable
 * @property-read mixed $images
 * @property-read Collection<int, AgentWebsiteUrl> $links
 * @property-read int|null $links_count
 * @property-read Collection<int, ChunkReference> $references
 * @property-read int|null $references_count
 *
 * @method static ChunkQueryBuilder<static>|Chunk configureSearchAccuracy(string $accuracyLevel, array $config)
 * @method static ChunkQueryBuilder<static>|Chunk hybridSearchForAgent(int $agentId, string $query, string $vectorString, int $limit = 4)
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
            // Encrypt the human-readable content at rest. The searchable
            // embedding columns stay plaintext (vector/BM25 search needs them).
            'chunk' => 'encrypted',
            'summary' => 'encrypted',
            'questions' => 'encrypted',
            'embedding' => Vector::class,
            'keywords' => 'json',
            'metadata' => 'json',
        ];
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

    public function references(): HasMany
    {
        return $this->hasMany(ChunkReference::class);
    }

    public function links(): HasManyThrough
    {
        return $this->hasManyThrough(
            AgentWebsiteUrl::class,
            ChunkReference::class,
            'chunk_id',      // Foreign key on ChunkReference
            'id',          // Foreign key on Link
            'id',            // Local key on Chunk
            'referenceable_id' // Local key on ChunkReference
        )->where('chunk_references.referenceable_type', AgentWebsiteUrl::class);
    }

    public function page(): array
    {
        $page = new PageData(
            pageNumber: $this->chunk_index,
            content: $this->chunk,
            checksum: $this->checksum,
        );

        $nextId = $this->chunk_index + 1;
        $prevId = $this->chunk_index - 1;

        $chunks = Chunk::query()
            ->select(['chunk', 'checksum', 'chunk_index'])
            ->where('chunkable_type', $this->chunkable_type)
            ->where('chunkable_id', $this->chunkable_id)
            ->whereIn('chunk_index', [$nextId, $prevId])
            ->get();

        $next = $chunks->where('chunk_index', $nextId)->first();

        if ($next) {
            $next = PageData::fromChunkModel($next);
        }

        $prev = $chunks->where('chunk_index', $prevId)->first();

        if ($prev) {
            $prev = PageData::fromChunkModel($prev);
        }

        return [
            'page' => $page,
            'previous' => $prev,
            'next' => $next,
        ];
    }
}
