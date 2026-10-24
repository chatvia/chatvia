<?php

use Domain\Agent\Models\Agent;
use Domain\Evaluation\Models\EvalCase;
use Domain\Evaluation\Models\EvalResult;
use Domain\Evaluation\Models\EvalRun;
use Domain\Evaluation\Queries\EvalRegressionQuery;

/**
 * A regression is a case that fails in a run but passed in the agent's
 * completed run before it. New cases and the agent's first run have none.
 */
function erqResult(EvalRun $run, ?EvalCase $case, bool $passed): void
{
    EvalResult::create(['run_id' => $run->id, 'case_id' => $case?->id, 'case_name' => $case->name ?? 'gone', 'scorer_type' => 'contains', 'has_passed' => $passed]);
}

it('names the cases that passed in the run before and fail now', function (): void {
    $agent = Agent::factory()->create();
    [$refunds, $hours, $fresh] = [
        EvalCase::factory()->forAgent($agent)->create(['name' => 'Refunds']),
        EvalCase::factory()->forAgent($agent)->create(['name' => 'Hours']),
        EvalCase::factory()->forAgent($agent)->create(['name' => 'New case']),
    ];

    $before = EvalRun::factory()->forAgent($agent)->completed()->create();
    erqResult($before, $refunds, true);
    erqResult($before, $hours, false);

    // A failed (stopped) run in between is not the one to compare with.
    EvalRun::factory()->forAgent($agent)->failed()->create();

    $now = EvalRun::factory()->forAgent($agent)->completed()->create();
    erqResult($now, $refunds, false);
    erqResult($now, $hours, false);
    erqResult($now, $fresh, false);

    expect(app(EvalRegressionQuery::class)->regressedCases($now))->toBe(['Refunds']);
});

it('finds none in the agent\'s first run', function (): void {
    $agent = Agent::factory()->create();
    $run = EvalRun::factory()->forAgent($agent)->completed()->create();
    erqResult($run, EvalCase::factory()->forAgent($agent)->create(), false);

    expect(app(EvalRegressionQuery::class)->regressedCases($run))->toBe([]);
});
