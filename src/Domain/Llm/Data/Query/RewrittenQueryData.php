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
        /** One short question to ask the visitor, when the message is too vague to search. */
        public ?string $clarifyingQuestion = null,
    ) {}

    /** The message unchanged, used when rewriting is off or fails. */
    public static function unchanged(string $query): self
    {
        return new self(language: null, query: $query);
    }

    /** This rewrite, with the given language when the model detected none. */
    public function orLanguage(?string $language): self
    {
        return $this->language === null ? new self($language, $this->query, $this->clarifyingQuestion) : $this;
    }

    /** This rewrite searching for the given query instead, keeping its language and question. */
    public function withQuery(string $query): self
    {
        return new self($this->language, $query, $this->clarifyingQuestion);
    }
}
