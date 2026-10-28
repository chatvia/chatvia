<?php

namespace Domain\Knowledge\Source\Text\Models;

use Database\Factories\SourceTextFactory;
use Domain\Agent\Models\Agent;
use Domain\Agent\Models\AgentSource;
use Domain\Knowledge\Document\Concerns\HasStorageFile;
use Domain\Knowledge\Ingestion\Contracts\ChunkableContract;
use Domain\Knowledge\Ingestion\Models\Chunk;
use Domain\Knowledge\Source\Concerns\Chunkable;
use Domain\Knowledge\Source\Enums\SourceStatus;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Carbon;
use Support\Concerns\HasExternalId;

/**
 * A block of plain text an owner typed or pasted as knowledge. The text itself
 * lives in storage (`filepath`); the row keeps an excerpt and the ingestion
 * state. Agents use it through the polymorphic `agent_sources` pivot.
 *
 * @property int $id
 * @property string $external_id
 * @property int $workspace_id
 * @property string|null $language
 * @property string|null $document_classification
 * @property string $filepath
 * @property string $excerpt
 * @property int $length This is the number of pages not characters in the text
 * @property int|null $expected_chunks
 * @property string $checksum
 * @property string|null $internal_ingestion_job_id
 * @property string|null $internal_chunking_batch_id
 * @property SourceStatus $status
 * @property int $took_in_seconds
 * @property string|null $failure_reason
 * @property int $retrieval_count
 * @property Carbon|null $last_retrieved_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string|null $content Dynamic attribute populated from storage at runtime
 * @property-read AgentSource|null $pivot
 * @property-read Collection<int, Agent> $agents
 * @property-read int|null $agents_count
 * @property-read Collection<int, Chunk> $chunks
 * @property-read int|null $chunks_count
 * @property-read Chunk|null $summary
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SourceText newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SourceText newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SourceText query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SourceText whereChecksum($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SourceText whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SourceText whereDocumentClassification($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SourceText whereExcerpt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SourceText whereExpectedChunks($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SourceText whereExternalId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SourceText whereFailureReason($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SourceText whereFilepath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SourceText whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SourceText whereInternalChunkingBatchId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SourceText whereInternalIngestionJobId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SourceText whereLanguage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SourceText whereLastRetrievedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SourceText whereLength($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SourceText whereRetrievalCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SourceText whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SourceText whereTookInSeconds($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SourceText whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SourceText whereWorkspaceId($value)
 *
 * @mixin \Eloquent
 */
#[Guarded(['id'])]
class SourceText extends Model implements ChunkableContract
{
    /** The longest text a source takes: past this a text is a file. */
    public const int MAX_CONTENT_CHARS = 1_000_000;

    /** @use HasFactory<SourceTextFactory> */
    use Chunkable, HasExternalId, HasFactory, HasStorageFile;

    /**
     * @return MorphToMany<Agent, $this, AgentSource>
     */
    public function agents(): MorphToMany
    {
        return $this->morphToMany(Agent::class, 'sourceable', 'agent_sources')
            ->using(AgentSource::class);
    }

    protected function casts(): array
    {
        return [
            'status' => SourceStatus::class,
            'last_retrieved_at' => 'datetime',
        ];
    }

    /**
     * Clears the text's chunks, the summary chunk (index 0) included, so the
     * next ingestion builds them all again, and marks it to be ingested.
     */
    public function retrain(): void
    {
        $this->deleteChunks();

        $this->update([
            'language' => null,
            'document_classification' => null,
            'status' => SourceStatus::Pending,
        ]);
    }
}
