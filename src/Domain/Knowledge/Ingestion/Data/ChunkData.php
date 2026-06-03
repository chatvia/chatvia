<?php

namespace Domain\Knowledge\Ingestion\Data;

use Spatie\LaravelData\Data;

class ChunkData extends Data
{
    public function __construct(
        public string $text,
        public array $images = [],
        public ?string $type = null,
        public ?string $original = null,
        public ?string $enhanced = null,
    ) {}
}
