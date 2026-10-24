<?php

declare(strict_types=1);

namespace Domain\Agent\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Something that shapes an agent's answers changed: its model, its
 * instructions or a Q&A entry. (A source finishing training has its own
 * event.) With "Run evals after changes" on, it queues an eval run.
 */
final readonly class AgentAnswersChanged
{
    use Dispatchable;

    public function __construct(
        public int $agentId,
    ) {}
}
