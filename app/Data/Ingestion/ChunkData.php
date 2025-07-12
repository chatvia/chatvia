<?php

namespace App\Data\Ingestion;

use Spatie\LaravelData\Data;

class ChunkData extends Data
{
    public function __construct(
        public ChunkTypeEnum $type,
        public string $original,
        public string $enhanced
    ) {}
}
