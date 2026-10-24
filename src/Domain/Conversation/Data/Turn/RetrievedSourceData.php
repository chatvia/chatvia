<?php

declare(strict_types=1);

namespace Domain\Conversation\Data\Turn;

use Domain\Knowledge\Retrieval\Enums\SourceKind;
use Spatie\LaravelData\Data;

/**
 * One knowledge chunk retrieved for a turn: its text (for the model), how
 * relevant the relevance check found it, and how its source is named (a
 * label, a public link, a PDF page). {@see self::forClients()} is what the
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
        /** The day the chunk's text entered the knowledge base (Y-m-d), so the model can prefer newer facts. */
        public ?string $added_on = null,
    ) {}

    /**
     * A turn's sources as the chat clients show them under an answer: one
     * entry per place the answer drew on (a source, a PDF page, a website
     * page), best first, with how many of its passages were used. The chunks
     * of one source share its name, so one entry per chunk would list the
     * same name again and again.
     *
     * @param  iterable<int, self>  $sources  best first
     * @return list<SourceRefData>
     */
    public static function forClients(iterable $sources): array
    {
        /** @var array<string, SourceRefData> $entries */
        $entries = [];

        foreach ($sources as $source) {
            $key = implode('|', [$source->sourceable_type, $source->sourceable_id, $source->url, $source->page]);

            // The first (best) chunk names the entry; later chunks only count.
            $entries[$key] = isset($entries[$key])
                ? $entries[$key]->withAnotherPassage()
                : $source->forClient(passages: 1);
        }

        return array_values($entries);
    }

    /**
     * One chunk's source as the chat clients show it: no chunk text and no
     * internal ids.
     */
    public function forClient(int $passages): SourceRefData
    {
        return new SourceRefData(
            id: $this->external_id,
            label: $this->label,
            kind: $this->kind->value,
            url: $this->url,
            page: $this->page,
            passages: $passages,
        );
    }
}
