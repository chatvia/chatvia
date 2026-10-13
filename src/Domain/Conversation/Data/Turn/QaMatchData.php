<?php

declare(strict_types=1);

namespace Domain\Conversation\Data\Turn;

use Spatie\LaravelData\Data;

/**
 * A curated Q&A entry with a question that matched the visitor's message as
 * a whole. Its answer is sent as-is, with no LLM call.
 */
class QaMatchData extends Data
{
    public function __construct(
        public int $id,
        public ?string $questions,
        public string $answer,
        public float $confidenceScore,
        public string $title = '',
    ) {}
}
