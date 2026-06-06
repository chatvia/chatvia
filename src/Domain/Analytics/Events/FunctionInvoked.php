<?php

namespace Domain\Analytics\Events;

use DateTimeInterface;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A webhook (HTTP) function tool was invoked by an agent. Carries the label
 * fields so the analytics rollup can survive function deletion.
 */
class FunctionInvoked
{
    use Dispatchable;

    public function __construct(
        public readonly int $agentId,
        public readonly int $workspaceId,
        public readonly int $functionId,
        public readonly string $name,
        public readonly ?string $description,
        public readonly DateTimeInterface $occurredAt,
    ) {}
}
