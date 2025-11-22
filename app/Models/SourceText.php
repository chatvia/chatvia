<?php

namespace App\Models;

use App\Contracts\ChunkableContract;
use App\Jobs\Ingestion\TextIngestionJob;
use App\Traits\Chunkable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class SourceText extends Model implements ChunkableContract
{
    use Chunkable;

    protected $guarded = ['id'];

    public function agents(): MorphToMany
    {
        return $this->morphToMany(Agent::class, 'sourceable', 'agent_sources')
            ->using(AgentSource::class)
            ->withTimestamps();
    }

    public function startTraining(?int $agentId = null): void
    {
        // If agentId is provided, train for that specific agent
        // Otherwise, train for all agents this text is attached to
        if ($agentId) {
            TextIngestionJob::dispatch($this, $agentId);
        } else {
            $this->agents->each(function ($agent) {
                TextIngestionJob::dispatch($this, $agent->id);
            });
        }
    }

    public function retrain()
    {
        $this->chunks()->delete();

        $this->update([
            'language' => null,
            'document_classification' => null,
            'status' => 'pending',
        ]);
    }
}
