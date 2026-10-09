<?php

namespace Domain\Llm\Data\Query;

use Spatie\LaravelData\Data;

/**
 * A visitor's message rewritten for knowledge-base search, plus the language
 * the model detected (ISO 639-1, or null when it gave none).
 */
final class RewrittenQueryData extends Data
{
    public function __construct(
        public ?string $language,
        public string $query,
    ) {}

    /** The message unchanged, used when rewriting is off or fails. */
    public static function unchanged(string $query): self
    {
        return new self(language: null, query: $query);
    }
}
