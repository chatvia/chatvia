<?php

declare(strict_types=1);

namespace Domain\Evaluation\Jobs;

use Domain\Agent\Models\Agent;
use Domain\Evaluation\Actions\RunEvalSuiteAction;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Queue\Attributes\UniqueFor;
use Support\Exceptions\DomainValidationException;

/**
 * The eval run "Run evals after changes" queues a while after an agent's
 * answers changed ({@see RunEvalSuiteAction}). One per agent at a time, so a
 * burst of edits leads to one run that sees all of them. A workspace at its
 * message limit skips the run.
 */
// A failed start is not retried; the next change queues another run.
#[Tries(1)]
// Longer than the delay the job is queued with, so the burst stays one run.
#[UniqueFor(1200)]
class RunAutoEvalJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /** How long after the first change the run starts. */
    public const int DELAY_SECONDS = 600;

    public function __construct(
        public readonly int $agentId,
    ) {}

    public function uniqueId(): string
    {
        return (string) $this->agentId;
    }

    public function handle(RunEvalSuiteAction $action): void
    {
        $agent = Agent::query()->find($this->agentId);

        if ($agent instanceof Agent) {
            rescue(static fn (): mixed => $action->execute($agent), report: static fn (\Throwable $e): bool => ! $e instanceof DomainValidationException);
        }
    }
}
