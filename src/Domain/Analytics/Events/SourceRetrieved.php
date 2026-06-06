<?php

namespace Domain\Analytics\Events;

use DateTimeInterface;
use Illuminate\Foundation\Events\Dispatchable;

class SourceRetrieved
{
    use Dispatchable;

    /**
     * @param  array<int, array{type: string, id: int}>  $sources  retrieved (sourceable_type, sourceable_id) pairs
     */
    public function __construct(
        public readonly int $agentId,
        public readonly int $workspaceId,
        public readonly array $sources,
        public readonly DateTimeInterface $occurredAt,
    ) {}
}
