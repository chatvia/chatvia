<?php

declare(strict_types=1);

namespace Domain\Analytics\Events;

use DateTimeInterface;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Knowledge sources were used to answer a message (one event per retrieval
 * batch).
 */
class SourceRetrieved
{
    use Dispatchable;

    /**
     * @param  array<int, array{type: string, id: int, name: ?string, mime: ?string}>  $sources  retrieved sources (morph alias, id), with the name and mime they had then, so the rollup still names a source after it is deleted
     */
    public function __construct(
        public readonly int $agentId,
        public readonly int $workspaceId,
        public readonly array $sources,
        public readonly DateTimeInterface $occurredAt,
    ) {}
}
