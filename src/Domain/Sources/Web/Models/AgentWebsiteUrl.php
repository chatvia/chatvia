<?php

namespace Domain\Sources\Web\Models;

use Domain\Agents\Models\Agent;
use Domain\Ingestion\Contracts\ChunkableContract;
use Domain\Ingestion\Jobs\Website\WebsiteUrlTrainJob;
use Domain\Ingestion\Models\Chunk;
use Domain\Ingestion\Models\ChunkReference;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $source_website_id
 * @property string $url
 * @property string|null $text
 * @property string|null $checksum
 * @property string $status
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

    public function train(Agent $agent): void
    {
        $this->update(['status' => 'pending']);

        WebsiteUrlTrainJob::dispatch($this, $agent);
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
        )->where('chunk_references.referenceable_type', AgentWebsiteUrl::class);
    }

    protected function casts(): array
    {
        return [
            'crawled_at' => 'datetime',
        ];
    }
}
