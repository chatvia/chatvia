<?php

namespace App\Models;

use App\Contracts\ChunkableContract;
use App\Jobs\Ingestion\Text\TextIngestionJob;
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

    public function startTraining(?Agent $agent = null): void
    {
        // If agentId is provided, train for that specific agent
        // Otherwise, train for all agents this text is attached to
        if ($agent) {
            TextIngestionJob::dispatch($this, $agent);
        } else {
            $this->agents->each(function ($agent) {
                TextIngestionJob::dispatch($this, $agent);
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
