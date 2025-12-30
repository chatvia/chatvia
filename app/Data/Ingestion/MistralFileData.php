<?php

namespace App\Data\Ingestion;

use Spatie\LaravelData\Data;

class MistralFileData extends Data
{
    public function __construct(
        public string $fileId,
    ) {}
}
