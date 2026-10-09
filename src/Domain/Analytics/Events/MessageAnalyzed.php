<?php

namespace Domain\Analytics\Events;

use DateTimeInterface;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A visitor message got its sentiment and tone. A null dimension is one the
 * plan or the agent has switched off, so it is not rolled up.
 */
class MessageAnalyzed
{
    use Dispatchable;

    public function __construct(
        public readonly int $agentId,
        public readonly int $workspaceId,
        public readonly ?string $sentiment,
        public readonly ?string $tone,
        public readonly DateTimeInterface $occurredAt,
    ) {}
}
