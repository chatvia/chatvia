<?php

namespace App\Http\Controllers\Agent\Source\Qa;

use App\Http\Requests\Agent\Source\Qa\CreateAgentQaRequest;
use App\Http\Requests\Agent\Source\Qa\UpdateAgentQaRequest;
use Domain\Agent\Data\Resources\Account\AgentResourceData;
use Domain\Agent\Models\Agent;
use Domain\Knowledge\Source\Qa\Actions\CreateAgentQaAction;
use Domain\Knowledge\Source\Qa\Actions\UpdateAgentQaAction;
use Domain\Knowledge\Source\Qa\Data\QaRowData;
use Domain\Knowledge\Source\Qa\Models\AgentQuestionAnswer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The agent's curated Q&A entries: the page, the list, and creating, editing
 * and deleting an entry. Agent access is checked by the route middleware; the
 * `agent.sources.train.qa` and `agent.sources.delete` permissions too. The
 * bound `{qa}` is 404-checked against the agent by `can.access.agent`.
 */
class AgentQaController
{
    public function index(Request $request, Agent $agent): Response|JsonResponse
    {
        if ($request->wantsJson()) {
            $rows = AgentQuestionAnswer::query()
                ->where('agent_id', $agent->id)
                ->latest()
                ->get()
                ->map(fn (AgentQuestionAnswer $qa): QaRowData => QaRowData::fromModel($qa));

            return response()->json(['data' => $rows]);
        }

        return Inertia::render('dashboard/agents/pages/Sources/Qa', [
            'agent' => AgentResourceData::fromModel($agent),
        ]);
    }

    public function show(Agent $agent, AgentQuestionAnswer $qa): JsonResponse
    {
        return response()->json(QaRowData::fromModel($qa));
    }

    public function store(CreateAgentQaRequest $request, Agent $agent, CreateAgentQaAction $createQa): RedirectResponse
    {
        $createQa->execute($agent, $request->toData());

        return back();
    }

    public function update(UpdateAgentQaRequest $request, Agent $agent, AgentQuestionAnswer $qa, UpdateAgentQaAction $updateQa): RedirectResponse
    {
        $updateQa->execute($qa, $request->toData());

        return back();
    }

    public function destroy(Agent $agent, AgentQuestionAnswer $qa): JsonResponse
    {
        $qa->delete();

        return response()->json(['message' => __('Q&A entry deleted successfully')]);
    }
}
