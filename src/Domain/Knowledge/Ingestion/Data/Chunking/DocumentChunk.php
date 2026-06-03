<?php

namespace Domain\Knowledge\Ingestion\Data\Chunking;

use Spatie\LaravelData\Data;

class DocumentChunk extends Data
{
    public function __construct(
        public string $chunk,
        public string $summary,
        public array $questions,
        public float $cost,
    ) {}
}
