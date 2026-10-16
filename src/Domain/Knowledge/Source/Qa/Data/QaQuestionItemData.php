<?php

namespace Domain\Knowledge\Source\Qa\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One phrasing of a Q&A entry's question, with a stable content hash the
 * editor uses as a list key.
 */
#[TypeScript]
class QaQuestionItemData extends Data
{
    public function __construct(
        public string $id,
        public string $question,
    ) {}

    public static function fromQuestion(string $question): self
    {
        return new self(
            id: md5($question),
            question: $question,
        );
    }
}
