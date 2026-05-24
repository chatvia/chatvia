<?php

namespace Domain\Analytics\Data;

use Spatie\LaravelData\Data;

class ContentAnalysisData extends Data
{
    public function __construct(
        public ?string $questions,
        public string $document_type,
        public DocumentMetadataData $metadata,
        public string $language,
        public float $confidence_score,
    ) {}
}
