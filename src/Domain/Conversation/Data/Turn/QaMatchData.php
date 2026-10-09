<?php

namespace Domain\Conversation\Data\Turn;

use Spatie\LaravelData\Data;

/**
 * A curated Q&A entry whose question phrase matched the visitor's message
 * exactly. Its answer is sent as-is, with no LLM call.
 */
class QaMatchData extends Data
{
    public function __construct(
        public int $id,
        public ?string $questions,
        public string $answer,
        public float $confidenceScore,
    ) {}
}
