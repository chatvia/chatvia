<?php

use Domain\Agent\Models\Agent;
use Domain\Evaluation\Actions\RunEvalSuiteAction;
use Domain\Evaluation\Jobs\ScoreEvalCaseJob;
use Domain\Evaluation\Models\EvalCase;
use Domain\Evaluation\Models\EvalRun;
use Domain\Llm\Models\Llm;
use Illuminate\Bus\PendingBatch;
use Illuminate\Support\Facades\Bus;
use Illuminate\Validation\ValidationException;

/**
 * RunEvalSuite snapshots a run and fans the ACTIVE cases out as a batch. The
 * contract we pin: inactive cases never run, the run is snapshotted as
 * "running", an empty suite is finalized inline (never left stuck), and a
 * workspace with no message credits can't start a suite at all (each scored
 * case bills one message). The credit gate is stubbed open by default.
 */
beforeEach(function (): void {
    fakeMessageCreditGate();
});

function agentWithLlm(): Agent
{
    return Agent::factory()->create(['llm_id' => Llm::factory()->create()->id]);
}

function evalCaseFor(Agent $agent, bool $active): EvalCase
{
    return EvalCase::create([
        'agent_id' => $agent->id,
        'agent_name' => $agent->name,
        'workspace_id' => $agent->user_id,
        'name' => 'case',
        'input' => 'ping',
        'scorer_type' => 'contains',
        'scorer_config' => ['value' => 'pong'],
        'active' => $active,
    ]);
}

it('snapshots a running run and batches one job per active case', function (): void {
    Bus::fake();
    $agent = agentWithLlm();

    evalCaseFor($agent, active: true);
    evalCaseFor($agent, active: true);
    evalCaseFor($agent, active: false);

    $run = app(RunEvalSuiteAction::class)->execute($agent);

    expect($run->status)->toBe(EvalRun::STATUS_RUNNING)
        ->and($run->total_cases)->toBe(2)
        ->and($run->model_snapshot)->toBe($agent->llm->internal_name);

    Bus::assertBatched(fn (PendingBatch $batch): bool => $batch->jobs->count() === 2
        && $batch->jobs->every(fn ($job): bool => $job instanceof ScoreEvalCaseJob));
});

it('finalizes immediately when there are no active cases', function (): void {
    Bus::fake();
    $agent = agentWithLlm();

    evalCaseFor($agent, active: false);

    $run = app(RunEvalSuiteAction::class)->execute($agent);

    expect($run->status)->toBe(EvalRun::STATUS_COMPLETED)
        ->and($run->total_cases)->toBe(0);

    Bus::assertNothingBatched();
});

it('refuses to start a suite when the workspace is out of messages', function (): void {
    Bus::fake();
    $agent = agentWithLlm();

    evalCaseFor($agent, active: true);

    fakeMessageCreditGate(false);

    expect(fn () => app(RunEvalSuiteAction::class)->execute($agent))
        ->toThrow(ValidationException::class);

    expect(EvalRun::query()->forAgent($agent->id)->count())->toBe(0);

    Bus::assertNothingBatched();
});
