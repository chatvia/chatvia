<?php

namespace Domain\Knowledge\Ingestion\Data;

use Spatie\LaravelData\Data;

class ChunkIngestionData extends Data
{
    public function __construct(
        public int $agent_id,
        public string $chunkable_type,
        public int $chunkable_id,
        public int $chunk_index,
        public int $expected_chunks,
        public string $chunk_text,
        public array $metadata = [],
        public array $chunk_images = [],
        public array $chunk_tables = [],
        public ?string $prev_chunk = null,
        public ?string $next_chunk = null,
    ) {}
}
