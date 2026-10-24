<?php

declare(strict_types=1);

namespace Domain\Evaluation\Listeners;

use Domain\Agent\Models\Agent;
use Domain\Evaluation\Enums\EvalRunStatus;
use Domain\Evaluation\Events\EvalRunFinished;
use Domain\Evaluation\Notifications\EvalRegressedNotification;
use Domain\Evaluation\Queries\EvalRegressionQuery;
use Domain\Notification\Services\NotificationRecipients;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;

/**
 * Tells the people who may see an agent's evals when a finished run failed
 * cases that passed in the run before it, so a change that broke an answer is
 * caught before visitors notice. Queued, apart from the run's batch.
 */
class NotifyEvalRegression implements ShouldQueue
{
    /** Run only after the dispatching transaction has committed. */
    public bool $afterCommit = true;

    public function __construct(
        private readonly EvalRegressionQuery $regressions,
        private readonly NotificationRecipients $recipients,
    ) {}

    public function handle(EvalRunFinished $event): void
    {
        $run = $event->run;
        $agent = $run->agent;

        if ($run->status !== EvalRunStatus::Completed || ! $agent instanceof Agent) {
            return;
        }

        $cases = $this->regressions->regressedCases($run);

        if ($cases === []) {
            return;
        }

        Notification::send(
            $this->recipients->forAgent($agent, 'agent.evals.view'),
            new EvalRegressedNotification($run, $agent, count($cases), $cases[0]),
        );
    }
}
