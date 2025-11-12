<?php

namespace App\Models;

use App\Contracts\ChunkableContract;
use App\Traits\Chunkable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentYoutubeVideo extends Model implements ChunkableContract
{
    use Chunkable;

    protected $guarded = ['id'];

    protected $casts = [
        'duration' => 'integer',
        'length' => 'integer',
    ];

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function startTraining(): void
    {
        // TODO: Dispatch job to process YouTube video
        // YoutubeIngestionJob::dispatch($this);
    }

    public function retrain(): void
    {
        $this->chunks()->delete();
        $this->update([
            'status' => 'pending',
            'error_message' => null,
        ]);
        $this->startTraining();
    }
}
