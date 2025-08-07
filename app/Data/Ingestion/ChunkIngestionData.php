<?php

namespace App\Data\Ingestion;

use Spatie\LaravelData\Data;

class ChunkIngestionData extends Data
{
    public function __construct(
        public int $agent_id,
        public int $agent_file_id,
        public int $chunk_index,
        public int $total_chunks,
        public string $chunk_text,
        public array $metadata = [],
        public array $chunk_images = [],
        public ?string $prev_chunk = null,
        public ?string $next_chunk = null,
    )
    {
    }
}
