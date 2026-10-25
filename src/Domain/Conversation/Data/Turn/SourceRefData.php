<?php

declare(strict_types=1);

namespace Domain\Conversation\Data\Turn;

use Spatie\LaravelData\Data;

/**
 * One source as the chat clients list it under an answer: a label, where it
 * points (a public link, a PDF page), how many passages of it were used, the
 * numbers of the reference blocks it was (what an answer's `[n]` citation
 * markers point to) and the start of its best passage, for the citation card.
 * Built by {@see RetrievedSourceData::forClients()} and cached per message in
 * the live chat memory; entries cached before blocks existed have none.
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
        /** @var list<int> the 1-based reference blocks this source was */
        public array $blocks = [],
        public ?string $excerpt = null,
    ) {}

    /** The same source with one more passage, the reference block $block, counted; DTOs never mutate. */
    public function withAnotherPassage(int $block): self
    {
        return new self($this->id, $this->label, $this->kind, $this->url, $this->page, $this->passages + 1, [...$this->blocks, $block], $this->excerpt);
    }
}
