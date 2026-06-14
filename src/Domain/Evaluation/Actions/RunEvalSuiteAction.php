<?php

namespace Domain\Evaluation\Actions;

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Billing\Services\UserCreditSystem;
use Domain\Evaluation\Jobs\ScoreEvalCaseJob;
use Domain\Evaluation\Models\EvalCase;
use Domain\Evaluation\Models\EvalRun;
use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Bus;
use Illuminate\Validation\ValidationException;

/**
 * Kicks off a full eval suite for an agent: snapshots the run, fans the active
 * cases out as queued {@see ScoreEvalCaseJob}s on the analytics queue, and
 * finalizes the run from the batch `finally()` callback. A run with no active
 * cases is finalized inline so it never sits stuck on "running".
 */
class RunEvalSuiteAction
{
    public function __construct(
        private readonly UserCreditSystem $creditSystem,
    ) {}

    public function execute(Agent $agent, ?User $triggeredBy = null): EvalRun
    {
        // Evals bill like live chat — one message per scored case — so a
        // workspace that can't send messages can't start a suite either.
        if (! $this->creditSystem->forWorkspace($agent->user_id)->canSendMessages()) {
            throw ValidationException::withMessages([
                'run' => 'No message credits left — every eval case costs one message. Top up or upgrade to run the suite.',
            ]);
        }

        $cases = EvalCase::query()
            ->forAgent($agent->id)
            ->active()
            ->limit((int) config('chatvia.evals.max_cases_per_run', 100))
            ->get();

        $run = EvalRun::create([
            'agent_id' => $agent->id,
            'agent_name' => $agent->name,
            'workspace_id' => $agent->user_id,
            'status' => EvalRun::STATUS_RUNNING,
            'total_cases' => $cases->count(),
            'model_snapshot' => $agent->llm?->internal_name,
            'triggered_by_user_id' => $triggeredBy?->id,
            'triggered_by_name' => $triggeredBy?->name,
            'started_at' => now(),
        ]);

        if ($cases->isEmpty()) {
            return app(FinalizeEvalRunAction::class)->execute($run);
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
}
