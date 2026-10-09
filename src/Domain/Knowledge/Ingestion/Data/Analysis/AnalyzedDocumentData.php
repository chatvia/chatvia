<?php

namespace Domain\Knowledge\Ingestion\Data\Analysis;

use Domain\Llm\Data\Chat\ChatUsageData;
use Spatie\LaravelData\Data;

/**
 * The result of one document analysis call: the classification and the token
 * usage the provider reported for it (the prompt holds the whole document, so
 * the usage is what prices the call).
 */
final class AnalyzedDocumentData extends Data
{
    public function __construct(
        public ContentAnalysisData $analysis,
        public ?ChatUsageData $usage,
    ) {}
}
