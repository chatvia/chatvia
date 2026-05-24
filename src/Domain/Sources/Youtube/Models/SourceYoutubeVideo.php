<?php

namespace Domain\Sources\Youtube\Models;

use Domain\Ingestion\Contracts\ChunkableContract;
use Support\Traits\Chunkable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Auth\Models\User;
use Domain\Agents\Models\Agent;

class SourceYoutubeVideo extends Model implements ChunkableContract
{
    use Chunkable;

    protected $guarded = ['id'];

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
            $this->agents->each(function ($agent) {
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
