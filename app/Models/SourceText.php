<?php

namespace App\Models;

use App\Contracts\ChunkableContract;
use App\Data\Ingestion\OCR\PageData;
use App\Jobs\Ingestion\Text\TextIngestionJob;
use App\Traits\Chunkable;
use App\Traits\HasStorageFile;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Collection;

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
