<?php

declare(strict_types=1);

namespace Domain\Evaluation\Listeners;

use Domain\Agent\Events\AgentAnswersChanged;
use Domain\Agent\Models\Agent;
use Domain\Evaluation\Jobs\RunAutoEvalJob;
use Domain\Evaluation\Models\EvalCase;
use Domain\Knowledge\Ingestion\Events\SourceTrained;

/**
 * Queues an eval run a while after an agent's answers changed (its model,
 * instructions, Q&A or a source that finished training), when the agent has
 * "Run evals after changes" on and at least one active case. A run that
 * passes fewer cases than the one before is reported by NotifyEvalRegression.
 */
class QueueAutoEvalRun
{
    public function handle(AgentAnswersChanged|SourceTrained $event): void
    {
        $agent = $event instanceof SourceTrained
            ? Agent::query()->where('external_id', $event->agentExternalId)->first()
            : Agent::query()->find($event->agentId);

        if (! $agent instanceof Agent || ! (bool) $agent->settings('evals')->get('auto_run')) {
            return;
        }

        if (! EvalCase::query()->forAgent($agent->id)->active()->exists()) {
            return;
        }

        RunAutoEvalJob::dispatch($agent->id)
            ->onQueue((string) config('chatvia.evals.queue', 'analytics'))
            ->delay(RunAutoEvalJob::DELAY_SECONDS);
    }
}
