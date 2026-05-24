<?php

namespace Domain\Ingestion\Data;

use Spatie\LaravelData\Data;

class ChunkData extends Data
{
    public function __construct(
        public string $text,
        public array $images = [],
    ) {}
}
