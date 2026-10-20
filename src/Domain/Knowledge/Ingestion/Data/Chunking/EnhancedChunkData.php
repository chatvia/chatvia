<?php

declare(strict_types=1);

namespace Domain\Knowledge\Ingestion\Data\Chunking;

use Spatie\LaravelData\Data;

/**
 * What enhancing a page produced, ready to be stored with its chunk: the text
 * to embed (summary, page and visual descriptions), the summary, the
 * newline-separated questions the page answers, the model that wrote the
 * summary and questions, and the image and table metadata of a document page.
 */
final class EnhancedChunkData extends Data
{
    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function __construct(
        public string $embeddingText,
        public string $summary,
        public ?string $questions,
        public string $model,
        public ?array $metadata,
    ) {}
}
