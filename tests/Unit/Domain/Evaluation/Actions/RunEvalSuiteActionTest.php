<?php

use App\Identity\Workspace\Models\MessageLimit;
use Domain\Agent\Models\Agent;
use Domain\Evaluation\Actions\RunEvalSuiteAction;
use Domain\Evaluation\Enums\EvalRunStatus;
use Domain\Evaluation\Events\EvalRunProgressed;
use Domain\Evaluation\Jobs\ScoreEvalCaseJob;
use Domain\Evaluation\Models\EvalCase;
use Domain\Evaluation\Models\EvalRun;
use Domain\Llm\Models\Llm;
use Illuminate\Bus\PendingBatch;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Support\Exceptions\DomainValidationException;

/**
 * RunEvalSuite snapshots a run and fans the ACTIVE cases out as a batch. The
 * contract we pin: inactive cases never run, the run is snapshotted as
 * "running", an empty suite is finalized inline (never left stuck), and a
 * workspace that reached its monthly message limit can't start a suite at all
 * (each scored case counts as one message).
 */
/** An agent with a chat model. */
function resAgent(): Agent
{
    return Agent::factory()->create(['llm_id' => Llm::factory()->create()->id]);
}

it('snapshots a running run and batches one job per active case', function (): void {
    Bus::fake();
    $agent = resAgent();

    EvalCase::factory()->forAgent($agent)->create();
    EvalCase::factory()->forAgent($agent)->create();
    EvalCase::factory()->forAgent($agent)->inactive()->create();

    $run = app(RunEvalSuiteAction::class)->execute($agent);

    expect($run->status)->toBe(EvalRunStatus::Running)
        ->and($run->total_cases)->toBe(2)
        ->and($run->model_snapshot)->toBe($agent->llm->name);

    Bus::assertBatched(fn (PendingBatch $batch): bool => $batch->jobs->count() === 2
        && $batch->jobs->every(fn ($job): bool => $job instanceof ScoreEvalCaseJob));
});

it('finalizes immediately when there are no active cases', function (): void {
    Bus::fake();
    $agent = resAgent();

    EvalCase::factory()->forAgent($agent)->inactive()->create();

    $run = app(RunEvalSuiteAction::class)->execute($agent);

    expect($run->status)->toBe(EvalRunStatus::Completed)
        ->and($run->total_cases)->toBe(0);

    Bus::assertNothingBatched();
});

it('refuses to start a suite when the workspace reached its monthly message limit', function (): void {
    Bus::fake();
    $agent = resAgent();

    EvalCase::factory()->forAgent($agent)->create();
    MessageLimit::factory()->forWorkspace($agent->workspace)->reached()->create();

    expect(fn () => app(RunEvalSuiteAction::class)->execute($agent))
        ->toThrow(DomainValidationException::class);

    expect(EvalRun::query()->forAgent($agent->id)->count())->toBe(0);

    Bus::assertNothingBatched();
});

it('broadcasts the new running run so it appears live on open Evals pages', function (): void {
    Bus::fake();
    Event::fake([EvalRunProgressed::class]);
    $agent = resAgent();

    EvalCase::factory()->forAgent($agent)->create();

    $run = app(RunEvalSuiteAction::class)->execute($agent);

    Event::assertDispatched(
        EvalRunProgressed::class,
        // Call broadcastWith() inside the closure so the assertion exercises
        // EvalRunResourceData::fromModel on the freshly-created in-memory run —
        // its int counts must be populated or the payload build throws.
        function (EvalRunProgressed $e) use ($run, $agent): bool {
            $payload = $e->broadcastWith();

            return $e->agentExternalId === $agent->external_id
                && $payload['run']['external_id'] === $run->external_id
                && $payload['run']['status'] === EvalRunStatus::Running->value
                && $payload['run']['passed_count'] === 0
                && $payload['run']['failed_count'] === 0;
        },
    );
});
