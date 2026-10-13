<?php

declare(strict_types=1);

namespace Domain\Conversation\Data\Turn;

use Domain\Knowledge\Retrieval\Enums\SourceKind;
use Spatie\LaravelData\Data;

/**
 * One knowledge chunk retrieved for a turn: its text (for the model), how
 * relevant the relevance check found it, and how its source is named (a
 * label, a public link, a PDF page). {@see self::forClient()} is what the
 * chat clients receive.
 */
class RetrievedSourceData extends Data
{
    /**
     * @param  array<string, string>  $images  the chunk's described images: storage path => description
     */
    public function __construct(
        public int $id,
        public ?string $external_id,
        public string $content,
        public ?float $relevance,
        public string $label,
        public SourceKind $kind,
        public ?string $url,
        public ?int $page,
        /** The source's morph alias (for example `source_file`). */
        public string $sourceable_type,
        public int $sourceable_id,
        public array $images = [],
    ) {}

    /**
     * The source as the chat clients show it under an answer: no chunk text
     * and no internal ids.
     *
     * @return array{id: string|null, label: string, kind: string, url: string|null, page: int|null}
     */
    public function forClient(): array
    {
        return [
            'id' => $this->external_id,
            'label' => $this->label,
            'kind' => $this->kind->value,
            'url' => $this->url,
            'page' => $this->page,
        ];
    }
}
