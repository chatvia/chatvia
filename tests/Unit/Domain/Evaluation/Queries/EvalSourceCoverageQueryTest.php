<?php

use App\Identity\Workspace\Models\User;
use Database\Factories\AgentFileFactory;
use Domain\Evaluation\Models\EvalResult;
use Domain\Evaluation\Models\EvalRun;
use Domain\Evaluation\Queries\EvalSourceCoverageQuery;

/**
 * Coverage counts the agent's sources the last finished run drew on, and
 * names the ones no case reached. Before the first finished run there is none.
 */
it('counts the sources the last finished run used and names the rest', function (): void {
    $agent = createAgent(User::factory()->create());
    $policy = AgentFileFactory::new()->create(['workspace_id' => $agent->workspace_id, 'filename' => 'policy.pdf']);
    $prices = AgentFileFactory::new()->create(['workspace_id' => $agent->workspace_id, 'filename' => 'prices.pdf']);
    $agent->files()->attach([$policy->id, $prices->id]);

    $run = EvalRun::factory()->forAgent($agent)->completed()->create();
    EvalResult::create(['run_id' => $run->id, 'case_name' => 'Refunds', 'scorer_type' => 'contains', 'has_passed' => true, 'source_refs' => [$policy->getMorphClass().':'.$policy->id]]);

    $coverage = app(EvalSourceCoverageQuery::class)->for($agent);

    expect($coverage?->total)->toBe(2)
        ->and($coverage?->tested)->toBe(1)
        ->and($coverage?->untested)->toBe(['prices.pdf']);
});
