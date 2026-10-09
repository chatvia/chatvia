<?php

namespace Domain\Analytics\Data\Recording;

use DateTimeInterface;
use Domain\Analytics\Events\FunctionInvoked;
use Spatie\LaravelData\Data;

/**
 * One webhook function call by an agent, with the function's label captured
 * at call time. Built from a {@see FunctionInvoked} event.
 */
class FunctionInvocationRecordData extends Data
{
    public function __construct(
        public int $agentId,
        public int $workspaceId,
        public int $functionId,
        public string $name,
        public ?string $description,
        public DateTimeInterface $occurredAt,
    ) {}

    public static function fromEvent(FunctionInvoked $event): self
    {
        return new self(
            agentId: $event->agentId,
            workspaceId: $event->workspaceId,
            functionId: $event->functionId,
            name: $event->name,
            description: $event->description,
            occurredAt: $event->occurredAt,
        );
    }
}
