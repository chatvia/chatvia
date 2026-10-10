<?php

namespace App\Http\Controllers\Agent\Eval;

use Domain\Agent\Models\Agent;
use Domain\Evaluation\Models\EvalRun;
use Illuminate\Http\RedirectResponse;

/**
 * Clears the agent's whole eval-run history. A single Eloquent call
 * (eval_results cascade on the FK), so it stays inline per the trivial-write
 * convention.
 */
class ClearAgentEvalRunsController
{
    public function __invoke(Agent $agent): RedirectResponse
    {
        EvalRun::query()->forAgent($agent->id)->delete();

        return to_route('agents.evals', $agent);
    }
}
