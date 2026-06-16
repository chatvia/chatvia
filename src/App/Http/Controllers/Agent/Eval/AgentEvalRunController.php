<?php

namespace App\Http\Controllers\Agent\Eval;

use Domain\Agent\Data\Resources\Account\AgentResourceData;
use Domain\Agent\Models\Agent;
use Domain\Evaluation\Data\Resources\EvalResultResourceData;
use Domain\Evaluation\Data\Resources\EvalRunResourceData;
use Domain\Evaluation\Models\EvalRun;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Eval runs: drill-down into one run (its summary + every per-case result) plus
 * the destructive history controls (delete one, clear all). Every action is
 * scoped to the agent so a run from another agent can't be touched by id; the
 * deletes are single Eloquent calls (eval_results cascade on the FK), so they
 * stay inline here per the trivial-write convention.
 */
class AgentEvalRunController
{
    public function show(Request $request, Agent $agent, EvalRun $run): Response
    {
        abort_if($run->agent_id !== $agent->id, 404);

        $run->loadMissing('triggeredByUser');
        $results = $run->results()->orderBy('id')->get();

        return Inertia::render('agents/Evals/Run', [
            'agent' => AgentResourceData::fromModel($agent),
            'run' => EvalRunResourceData::fromModel($run),
            'results' => EvalResultResourceData::collection($results),
        ]);
    }

    public function destroy(Request $request, Agent $agent, EvalRun $run): RedirectResponse
    {
        abort_if($run->agent_id !== $agent->id, 404);

        $run->delete();

        return to_route('agents.evals', $agent);
    }

    public function clear(Request $request, Agent $agent): RedirectResponse
    {
        EvalRun::query()->forAgent($agent->id)->delete();

        return to_route('agents.evals', $agent);
    }
}
