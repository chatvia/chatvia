<?php

namespace App\Models;

use App\Jobs\Crawler\WebsiteIngestionJob;
use App\Traits\Chunkable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentWebsite extends Model
{
    use Chunkable;

    protected $guarded = ['id'];

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    protected function casts(): array
    {
        return [
            'links' => 'json',
            'options' => 'json',
        ];
    }

    public function startTraining(): void
    {
        WebsiteIngestionJob::dispatch($this);
    }
}
