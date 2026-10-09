<?php

declare(strict_types=1);

namespace Domain\Knowledge\Ingestion\Data\Analysis;

use Spatie\LaravelData\Data;

/**
 * The document classification the analysis LLM returns (strict JSON schema):
 * the questions the document answers, its type, language, audience metadata
 * and the model's confidence. The summary chunk stores it.
 */
class ContentAnalysisData extends Data
{
    public function __construct(
        /** @var string[] */
        public array $questions,
        public string $document_type,
        public DocumentMetadataData $metadata,
        public string $language,
        public float $confidence_score,
    ) {}
}
