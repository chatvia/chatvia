<?php

namespace Domain\Analytics\Data\Recording;

use DateTimeInterface;
use Domain\Analytics\Events\SourceRetrieved;
use Spatie\LaravelData\Data;

/**
 * The knowledge sources one retrieval used, to add to the daily source
 * rollup. Built from a {@see SourceRetrieved} event.
 */
class SourceRetrievalRecordData extends Data
{
    /**
     * @param  list<RetrievedSourceData>  $sources
     */
    public function __construct(
        public int $agentId,
        public int $workspaceId,
        /** @var list<RetrievedSourceData> */
        public array $sources,
        public DateTimeInterface $occurredAt,
    ) {}

    public static function fromEvent(SourceRetrieved $event): self
    {
        return new self(
            agentId: $event->agentId,
            workspaceId: $event->workspaceId,
            sources: array_values(array_map(
                static fn (array $source): RetrievedSourceData => new RetrievedSourceData(
                    type: (string) $source['type'],
                    id: (int) $source['id'],
                    name: $source['name'] ?? null,
                    mime: $source['mime'] ?? null,
                ),
                $event->sources,
            )),
            occurredAt: $event->occurredAt,
        );
    }
}
