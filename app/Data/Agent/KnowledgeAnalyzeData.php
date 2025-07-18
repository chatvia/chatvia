<?php

namespace App\Data\Agent;

use Spatie\LaravelData\Data;

class KnowledgeAnalyzeData extends Data
{
    public function __construct(
        public int $pages,
        public string $mime_type,
        public bool $encrypted,
    ) {}
}
