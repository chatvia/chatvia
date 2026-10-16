<?php

declare(strict_types=1);

namespace Domain\Conversation\Data\Turn;

use Spatie\LaravelData\Data;

/**
 * One source as the chat clients list it under an answer: a label, where it
 * points (a public link, a PDF page) and how many passages of it were used.
 * Built by {@see RetrievedSourceData::forClients()} and cached per message in
 * the live chat memory.
 */
class SourceRefData extends Data
{
    public function __construct(
        public ?string $id,
        public string $label,
        public string $kind,
        public ?string $url,
        public ?int $page,
        public int $passages,
    ) {}

    /** The same source with one more passage counted; DTOs never mutate. */
    public function withAnotherPassage(): self
    {
        return new self($this->id, $this->label, $this->kind, $this->url, $this->page, $this->passages + 1);
    }
}
