<?php

namespace Domain\Knowledge\Source\Youtube\Models;

use App\Identity\Workspace\Models\User;
use Domain\Agent\Collections\AgentCollection;
use Domain\Agent\Models\Agent;
use Domain\Knowledge\Ingestion\Contracts\ChunkableContract;
use Domain\Knowledge\Ingestion\Models\Chunk;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;
use Support\Traits\Chunkable;

/**
 * @property int $id
 * @property int $user_id
 * @property string|null $language
 * @property string|null $document_classification
 * @property string $url
 * @property string|null $title
 * @property string|null $description
 * @property string|null $checksum
 * @property string|null $parser
 * @property string|null $parser_filepath
 * @property int|null $expected_chunks
 * @property int $length
 * @property string|null $internal_ingestion_job_id
 * @property string|null $internal_chunking_batch_id
 * @property string|null $external_parsing_job_id
 * @property string|null $metadata
 * @property string $status
 * @property int $took_in_seconds
 * @property string|null $failure_reason
 * @property int $retrieval_count
 * @property string|null $last_retrieved_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read AgentCollection<int, Agent> $agents
 * @property-read int|null $agents_count
 * @property-read Collection<int, Chunk> $chunks
 * @property-read int|null $chunks_count
 * @property-read Chunk|null $summary
 * @property-read User $user
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SourceYoutubeVideo newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SourceYoutubeVideo newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SourceYoutubeVideo query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SourceYoutubeVideo whereChecksum($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SourceYoutubeVideo whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SourceYoutubeVideo whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SourceYoutubeVideo whereDocumentClassification($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SourceYoutubeVideo whereExpectedChunks($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SourceYoutubeVideo whereExternalParsingJobId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SourceYoutubeVideo whereFailureReason($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SourceYoutubeVideo whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SourceYoutubeVideo whereInternalChunkingBatchId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SourceYoutubeVideo whereInternalIngestionJobId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SourceYoutubeVideo whereLanguage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SourceYoutubeVideo whereLastRetrievedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SourceYoutubeVideo whereLength($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SourceYoutubeVideo whereMetadata($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SourceYoutubeVideo whereParser($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SourceYoutubeVideo whereParserFilepath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SourceYoutubeVideo whereRetrievalCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SourceYoutubeVideo whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SourceYoutubeVideo whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SourceYoutubeVideo whereTookInSeconds($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SourceYoutubeVideo whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SourceYoutubeVideo whereUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SourceYoutubeVideo whereUserId($value)
 *
 * @mixin \Eloquent
 */
#[Guarded(['id'])]
class SourceYoutubeVideo extends Model implements ChunkableContract
{
    use Chunkable;

    protected $casts = [
        'duration' => 'integer',
        'length' => 'integer',
    ];

    public function agents(): BelongsToMany
    {
        return $this->belongsToMany(Agent::class, 'agent_youtube_video', 'source_youtube_video_id', 'agent_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function startTraining(?int $agentId = null): void
    {
        // TODO: Dispatch job to process YouTube video
        // If agentId is provided, train for that specific agent
        // Otherwise, train for all agents this video is attached to
        if ($agentId) {
            // YoutubeIngestionJob::dispatch($this, $agentId);
        } else {
            $this->agents->each(function ($agent): void {
                // YoutubeIngestionJob::dispatch($this, $agent->id);
            });
        }
    }

    public function retrain(?int $agentId = null): void
    {
        // Delete chunks only for the specific agent if provided
        if ($agentId) {
            $this->chunks()->where('agent_id', $agentId)->delete();
        } else {
            $this->chunks()->delete();
        }

        $this->update([
            'status' => 'pending',
            'error_message' => null,
        ]);
        $this->startTraining($agentId);
    }
}
