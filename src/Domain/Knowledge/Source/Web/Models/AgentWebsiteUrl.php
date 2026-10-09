<?php

namespace Domain\Knowledge\Source\Web\Models;

use Domain\Knowledge\Ingestion\Contracts\ChunkableContract;
use Domain\Knowledge\Ingestion\Models\Chunk;
use Domain\Knowledge\Ingestion\Models\ChunkReference;
use Domain\Knowledge\Source\Enums\SourceStatus;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Carbon;

/**
 * One discovered page of a SourceWebsite (table `source_website_urls`). It
 * owns no chunks itself: `chunk_references` link it to the website chunks its
 * content produced, so a page can be retrained or removed on its own.
 *
 * @property int $id
 * @property int $source_website_id
 * @property string $url
 * @property string|null $text
 * @property string|null $checksum
 * @property SourceStatus $status
 * @property string|null $internal_ingestion_job_id
 * @property string|null $internal_chunking_batch_id
 * @property string|null $failure_reason
 * @property int $retrieval_count
 * @property Carbon|null $crawled_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read SourceWebsite $agentWebsite
 * @property-read Collection<int, Chunk> $chunks
 * @property-read int|null $chunks_count
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgentWebsiteUrl newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgentWebsiteUrl newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgentWebsiteUrl query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgentWebsiteUrl whereChecksum($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgentWebsiteUrl whereCrawledAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgentWebsiteUrl whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgentWebsiteUrl whereFailureReason($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgentWebsiteUrl whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgentWebsiteUrl whereInternalChunkingBatchId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgentWebsiteUrl whereInternalIngestionJobId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgentWebsiteUrl whereRetrievalCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgentWebsiteUrl whereSourceWebsiteId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgentWebsiteUrl whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgentWebsiteUrl whereText($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgentWebsiteUrl whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgentWebsiteUrl whereUrl($value)
 *
 * @mixin \Eloquent
 */
#[Guarded(['id'])]
#[Table(name: 'source_website_urls')]
class AgentWebsiteUrl extends Model implements ChunkableContract
{
    public function agentWebsite(): BelongsTo
    {
        return $this->belongsTo(SourceWebsite::class, 'source_website_id');
    }

    /**
     * Values `chunk_references.referenceable_type` can hold for a URL: the
     * morph alias, plus the class name that workers still running the
     * previous release may write until they restart.
     *
     * @return list<string>
     */
    public static function referenceTypes(): array
    {
        return [(new self)->getMorphClass(), self::class];
    }

    public function chunks(): HasManyThrough
    {
        return $this->hasManyThrough(
            Chunk::class,
            ChunkReference::class,
            'referenceable_id', // Foreign key on ChunkReference pointing to Link
            'id',               // Foreign key on Chunk
            'id',               // Local key on Link
            'chunk_id'          // Local key on ChunkReference pointing to Chunk
        )->whereIn('chunk_references.referenceable_type', AgentWebsiteUrl::referenceTypes());
    }

    protected function casts(): array
    {
        return [
            'crawled_at' => 'datetime',
            'status' => SourceStatus::class,
        ];
    }
}
