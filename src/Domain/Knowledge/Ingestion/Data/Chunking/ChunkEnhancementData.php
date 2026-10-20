<?php

declare(strict_types=1);

namespace Domain\Knowledge\Ingestion\Data\Chunking;

use Spatie\LaravelData\Data;

/**
 * What the chunker LLM wrote about one page: a short summary that puts the page
 * in context, the questions the page answers and the model that wrote them
 * (its provider model id).
 */
final class ChunkEnhancementData extends Data
{
    /**
     * @param  list<string>  $questions
     */
    public function __construct(
        public string $summary,
        public array $questions,
        public string $model,
    ) {}
}
