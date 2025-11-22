<?php

namespace App\Data\Ingestion;

use Spatie\LaravelData\Data;

class DocumentData extends Data
{
    public function __construct(
        public string $id,
        public int $agent_id,
        public int $source_file_id,
        public ChunkData $chunk,
        public array $keywords,
        public array $document,
        public array $metadata,
    ) {}
}
