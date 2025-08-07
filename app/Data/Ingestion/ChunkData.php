<?php

namespace App\Data\Ingestion;

use Spatie\LaravelData\Data;

class ChunkData extends Data
{
    public function __construct(
        public string $text,
        public array $images = [],
    ) {}
}
