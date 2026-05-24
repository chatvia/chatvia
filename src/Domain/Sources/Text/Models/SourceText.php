<?php

namespace Domain\Sources\Text\Models;

use Domain\Ingestion\Contracts\ChunkableContract;
use Domain\Ingestion\Data\OCR\PageData;
use Domain\Ingestion\Jobs\Text\TextIngestionJob;
use Support\Traits\Chunkable;
use Support\Traits\HasStorageFile;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Collection;
use Domain\Agents\Models\Agent;
use Domain\Agents\Models\AgentSource;

class SourceText extends Model implements ChunkableContract
{
    use Chunkable, HasStorageFile;

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
