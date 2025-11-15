<?php

namespace App\Models;

use App\Contracts\ChunkableContract;
use App\Jobs\Ingestion\TextIngestionJob;
use App\Traits\Chunkable;
use Illuminate\Database\Eloquent\Model;

class AgentText extends Model implements ChunkableContract
{
    use Chunkable;

    protected $guarded = ['id'];

    public function startTraining(): void
    {
        TextIngestionJob::dispatch($this);
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
