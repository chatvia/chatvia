<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

class AgentWebsiteUrl extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'crawled_at' => 'datetime',
        ];
    }

    public function chunk(): HasOneThrough
    {
        return $this->hasOneThrough(
            Chunk::class,
            ChunkReference::class,
            'referenceable_id', // Foreign key on ChunkReference pointing to Link
            'id',               // Foreign key on Chunk
            'id',               // Local key on Link
            'chunk_id'          // Local key on ChunkReference pointing to Chunk
        )->where('chunk_references.referenceable_type', AgentWebsiteUrl::class);
    }
}
