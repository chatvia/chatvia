<?php

declare(strict_types=1);

namespace Domain\Evaluation\Listeners;

use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Services\TeamPermissionPolicy;
use Domain\Agent\Models\Agent;
use Domain\Evaluation\Events\EvalRunFinished;
use Domain\Evaluation\Notifications\EvalRunFinishedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Tells the person who started an eval run that it finished, as long as they
 * may still see the agent's evals. Queued, apart from the run's batch.
 */
class NotifyEvalRunFinished implements ShouldQueue
{
    public function __construct(
        private readonly TeamPermissionPolicy $permissions,
    ) {}

    public function handle(EvalRunFinished $event): void
    {
        $run = $event->run;
        $agent = $run->agent;
        $starter = $run->triggeredByUser;

        if (! $agent instanceof Agent || ! $starter instanceof User || ! $this->permissions->allowsOnAgent($starter, $agent, 'agent.evals.view')) {
            return;
        }

        $starter->notify(new EvalRunFinishedNotification($run, $agent));
    }
}
