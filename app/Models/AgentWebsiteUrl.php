<?php

namespace App\Models;

use App\Contracts\ChunkableContract;
use App\Jobs\Ingestion\Website\WebsiteUrlTrainJob;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class AgentWebsiteUrl extends Model implements ChunkableContract
{
    protected $table = 'source_website_urls';

    protected $guarded = ['id'];

    public function agentWebsite(): BelongsTo
    {
        return $this->belongsTo(SourceWebsite::class, 'source_website_id');
    }

    public function train(Agent $agent): void
    {
        $this->update(['status' => 'pending']);

        WebsiteUrlTrainJob::dispatch($this, $agent);
    }

    public function chunks(): HasManyThrough
    {
        return $this->hasManyThrough(
            Chunk::class,
            ChunkReference::class,
            'referenceable_id', // Foreign key on ChunkReference pointing to Link
            'id',               // Foreign key on Chunk
            'id',               // Local key on Link
            'chunk_id'          // Local key on ChunkReference pointing to Chunk
        )->where('chunk_references.referenceable_type', AgentWebsiteUrl::class);
    }

    protected function casts(): array
    {
        return [
            'crawled_at' => 'datetime',
        ];
    }
}
