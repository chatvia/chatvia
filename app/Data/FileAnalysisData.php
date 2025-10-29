<?php

namespace App\Data;

use Spatie\LaravelData\Data;

class FileAnalysisData extends Data
{
    public function __construct(
        public int $length,
        public array $metadata = [],
    ) {}
}
