<?php

declare(strict_types=1);

namespace Domain\Knowledge\Source\Qa\Data;

use Illuminate\Support\Arr;
use Spatie\LaravelData\Data;

/**
 * A curated question-and-answer entry as the owner writes it: a title, the
 * phrasings of the question, and the answer the agent must give word for word
 * when a visitor asks one of them.
 */
final class QaEntryData extends Data
{
    /**
     * @param  list<string>  $questions
     */
    public function __construct(
        public string $title,
        public array $questions,
        public string $answer,
    ) {}

    /**
     * The questions as stored: one per line, empty ones dropped.
     */
    public function questionLines(): string
    {
        return Arr::join(array_filter($this->questions), "\n");
    }
}
