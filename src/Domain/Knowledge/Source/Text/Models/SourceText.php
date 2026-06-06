<?php

namespace Domain\Knowledge\Source\Text\Models;

use Domain\Agent\Collections\AgentCollection;
use Domain\Agent\Models\Agent;
use Domain\Agent\Models\AgentSource;
use Domain\Knowledge\Ingestion\Contracts\ChunkableContract;
use Domain\Knowledge\Ingestion\Data\OCR\PageData;
use Domain\Knowledge\Ingestion\Models\Chunk;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Support\Traits\Chunkable;
use Support\Traits\HasStorageFile;

/**
 * @property int $id
 * @property string|null $external_id
 * @property int $user_id
 * @property string|null $language
 * @property string|null $document_classification
 * @property string $filepath
 * @property string $excerpt
 * @property int $length This is the number of pages not characters in the text
 * @property int|null $expected_chunks
 * @property string $checksum
 * @property string|null $internal_ingestion_job_id
 * @property string|null $internal_chunking_batch_id
 * @property string $status
 * @property int $took_in_seconds
 * @property string|null $failure_reason
 * @property int $retrieval_count
 * @property string|null $last_retrieved_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string|null $content Dynamic attribute populated from storage at runtime
 * @property-read AgentSource|null $pivot
 * @property-read AgentCollection<int, Agent> $agents
 * @property-read int|null $agents_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Chunk> $chunks
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
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SourceText whereUserId($value)
 *
 * @mixin \Eloquent
 */
#[Guarded(['id'])]
class SourceText extends Model implements ChunkableContract
{
    use Chunkable, HasStorageFile;

    /**
     * @return MorphToMany<Agent, $this, AgentSource>
     */
    public function agents(): MorphToMany
    {
        return $this->morphToMany(Agent::class, 'sourceable', 'agent_sources')
            ->using(AgentSource::class);
    }

    public function retrain(): void
    {
        $this->chunks()->delete();

        $this->update([
            'language' => null,
            'document_classification' => null,
            'status' => 'pending',
        ]);
    }

    public function pages(): Collection
    {
        $chunks = $this
            ->chunks()
            ->whereNot('chunk_index', 0)
            ->select('id', 'chunk_index', 'chunk', 'checksum')
            ->orderBy('chunk_index')->get();

        return collect($chunks)->map(function (Chunk $chunk) {
            return new PageData(
                pageNumber: $chunk->chunk_index,
                content: $chunk->chunk,
                checksum: $chunk->checksum,
            );
        });
    }
}
