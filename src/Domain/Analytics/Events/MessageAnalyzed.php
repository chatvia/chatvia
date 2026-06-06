<?php

namespace Domain\Analytics\Events;

use DateTimeInterface;
use Illuminate\Foundation\Events\Dispatchable;

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
