<?php

namespace App\Http\Controllers\Agent\Eval;

use App\Http\Requests\Agent\Eval\SaveEvalCaseRequest;
use Domain\Agent\Models\Agent;
use Domain\Evaluation\Models\EvalCase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * CRUD for an agent's eval cases. Each write is a single Eloquent call with no
 * cross-caller behaviour, so per convention it stays inline here rather than in
 * an Action. The route gates every method on `agent.evals.manage`; the explicit
 * `agent_id` check stops a case from another agent being edited via id.
 */
class AgentEvalCaseController
{
    public function store(SaveEvalCaseRequest $request, Agent $agent): RedirectResponse
    {
        abort_if($request->user()->cannot('view', $agent), 404);

        $data = $request->toData();

        EvalCase::create([
            'agent_id' => $agent->id,
            'agent_name' => $agent->name,
            'workspace_id' => $agent->user_id,
            'name' => $data->name,
            'input' => $data->input,
            'scorer_type' => $data->scorer_type->value,
            'scorer_config' => $data->scorer_config,
            'expected_output' => $data->expected_output,
            'active' => $data->active,
        ]);

        return back();
    }

    public function update(SaveEvalCaseRequest $request, Agent $agent, EvalCase $case): RedirectResponse
    {
        abort_if($request->user()->cannot('view', $agent), 404);
        abort_if($case->agent_id !== $agent->id, 404);

        $data = $request->toData();

        $case->update([
            'name' => $data->name,
            'input' => $data->input,
            'scorer_type' => $data->scorer_type->value,
            'scorer_config' => $data->scorer_config,
            'expected_output' => $data->expected_output,
            'active' => $data->active,
        ]);

        return back();
    }

    public function destroy(Request $request, Agent $agent, EvalCase $case): RedirectResponse
    {
        abort_if($request->user()->cannot('view', $agent), 404);
        abort_if($case->agent_id !== $agent->id, 404);

        $case->delete();

        return back();
    }
}
