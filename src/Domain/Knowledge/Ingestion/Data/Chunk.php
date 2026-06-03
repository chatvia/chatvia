<?php

namespace Domain\Knowledge\Ingestion\Data;

use Spatie\LaravelData\Data;

class Chunk extends Data
{
    public function __construct(
        public int $chunk_id,
        public int $level,
        public string $heading,
        public string $original_content,
        public ?string $enhanced_content,
        public array $embedding,
        public array $metadata,
    ) {}
}
