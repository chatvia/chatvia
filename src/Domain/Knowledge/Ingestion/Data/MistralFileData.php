<?php

namespace Domain\Knowledge\Ingestion\Data;

use Spatie\LaravelData\Data;

class MistralFileData extends Data
{
    public function __construct(
        public string $fileId,
    ) {}
}
