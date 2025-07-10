<?php

namespace App\Data\Ingestion;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\ArrayCast;

class DocumentData extends Data
{
    public function __construct(
        public string        $id,
        public int           $agent_id,
        public int           $agent_file_id,
        public ChunkData     $chunk,
        public array         $keywords,
        public array         $document,
        public array $metadata,
    ) {
    }
}
