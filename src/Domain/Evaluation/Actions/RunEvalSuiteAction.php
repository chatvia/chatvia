<?php

namespace Domain\Evaluation\Actions;

use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Services\MessageAllowance;
use Domain\Agent\Models\Agent;
use Domain\Evaluation\Enums\EvalRunStatus;
use Domain\Evaluation\Events\EvalRunProgressed;
use Domain\Evaluation\Jobs\ScoreEvalCaseJob;
use Domain\Evaluation\Models\EvalCase;
use Domain\Evaluation\Models\EvalRun;
use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Bus;
use Support\Exceptions\DomainValidationException;

/**
 * Kicks off a full eval suite for an agent: snapshots the run, fans the active
 * cases out as queued {@see ScoreEvalCaseJob}s on the analytics queue, and
 * finalizes the run from the batch `finally()` callback. A run with no active
 * cases is finalized inline so it never sits stuck on "running".
 */
class RunEvalSuiteAction
{
    public function __construct(
        private readonly MessageAllowance $allowance,
        private readonly FinalizeEvalRunAction $finalize,
    ) {}

    /**
     * @throws DomainValidationException when the workspace has reached its monthly message limit
     */
    public function execute(Agent $agent, ?User $triggeredBy = null): EvalRun
    {
        $this->ensureWorkspaceMaySendMessages($agent);

        $cases = EvalCase::query()
            ->forAgent($agent->id)
            ->active()
            ->limit((int) config('chatvia.evals.max_cases_per_run', 100))
            ->get();

        $run = EvalRun::create([
            'agent_id' => $agent->id,
            'agent_name' => $agent->name,
            'workspace_id' => $agent->workspace_id,
            'status' => EvalRunStatus::Running,
            'total_cases' => $cases->count(),
            // Set explicitly so the freshly-created in-memory model (which Eloquent
            // does not re-hydrate from the DB defaults) carries int counts for the
            // EvalRunProgressed broadcast below — fromModel() types them as int.
            'passed_count' => 0,
            'failed_count' => 0,
            'model_snapshot' => $agent->llm?->name,
            'triggered_by_user_id' => $triggeredBy?->id,
            'triggered_by_name' => $triggeredBy?->name,
            'started_at' => now(),
        ]);

        // Surface the new "running" row live on any open Evals page before the
        // cases start scoring — the finalize callback broadcasts the close-out.
        // Bind the actor in-memory so the broadcast resource carries the avatar
        // without a lazy load (which would throw under Model::shouldBeStrict()).
        $run->setRelation('triggeredByUser', $triggeredBy);
        EvalRunProgressed::dispatch($run, $agent->external_id);

        if ($cases->isEmpty()) {
            return $this->finalize->execute($run);
        }

        $runId = $run->id;

        $batch = Bus::batch($cases->map(fn (EvalCase $case): ScoreEvalCaseJob => new ScoreEvalCaseJob($run, $case))->all())
            ->name('Eval Run #'.$run->id.' — '.$agent->name)
            ->onQueue((string) config('chatvia.evals.queue', 'analytics'))
            ->finally(function (Batch $batch) use ($runId): void {
                $run = EvalRun::find($runId);

                if ($run !== null) {
                    app(FinalizeEvalRunAction::class)->execute($run, $batch->cancelled());
                }
            })
            ->dispatch();

        $run->update(['batch_id' => $batch->id]);

        return $run->refresh();
    }

    /**
     * Evals count like live chat — one message per scored case — so they
     * check the same monthly limit: a workspace that could not get a chat
     * reply cannot start a suite either.
     *
     * @throws DomainValidationException
     */
    private function ensureWorkspaceMaySendMessages(Agent $agent): void
    {
        if (! $this->allowance->canSend($agent->workspace_id)) {
            throw new DomainValidationException('run', __('This workspace has reached its monthly message limit. Every eval case counts as one message.'));
        }
    }
}
