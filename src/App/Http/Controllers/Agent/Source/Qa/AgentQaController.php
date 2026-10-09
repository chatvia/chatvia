<?php

namespace App\Http\Controllers\Agent\Source\Qa;

use App\Http\Requests\Agent\Source\Qa\CreateAgentQaRequest;
use App\Http\Requests\Agent\Source\Qa\UpdateAgentQaRequest;
use Domain\Agent\Data\Resources\Account\AgentResourceData;
use Domain\Agent\Models\Agent;
use Domain\Knowledge\Source\Enums\SourceStatus;
use Domain\Knowledge\Source\Qa\Actions\CreateAgentQaAction;
use Domain\Knowledge\Source\Qa\Actions\UpdateAgentQaAction;
use Domain\Knowledge\Source\Qa\Models\AgentQuestionAnswer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The agent's curated Q&A entries: the page, the list, and creating, editing
 * and deleting an entry. Agent access is checked by the route middleware; the
 * `agent.sources.train.qa` and `agent.sources.delete` permissions too.
 */
class AgentQaController
{
    public function index(Request $request, Agent $agent): Response|JsonResponse
    {
        if ($request->wantsJson()) {
            return response()->json(['data' => $this->qaList($agent)]);
        }

        return Inertia::render('agents/Sources/Qa', [
            'agent' => AgentResourceData::fromModel($agent),
        ]);
    }

    public function show(Agent $agent, int $id): JsonResponse
    {
        return response()->json($this->qaRow($this->agentQa($agent, $id)));
    }

    public function store(CreateAgentQaRequest $request, Agent $agent, CreateAgentQaAction $createQa): RedirectResponse
    {
        $createQa->execute($agent, $request->toData());

        return back();
    }

    public function update(UpdateAgentQaRequest $request, Agent $agent, int $id, UpdateAgentQaAction $updateQa): RedirectResponse
    {
        $updateQa->execute($this->agentQa($agent, $id), $request->toData());

        return back();
    }

    public function destroy(Agent $agent, int $id): JsonResponse
    {
        $this->agentQa($agent, $id)->delete();

        return response()->json(['message' => __('Q&A entry deleted successfully')]);
    }

    private function agentQa(Agent $agent, int $id): AgentQuestionAnswer
    {
        return AgentQuestionAnswer::query()
            ->where('agent_id', $agent->id)
            ->findOrFail($id);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function qaList(Agent $agent): Collection
    {
        return AgentQuestionAnswer::query()
            ->where('agent_id', $agent->id)
            ->latest()
            ->get()
            ->map(fn (AgentQuestionAnswer $qa): array => $this->qaRow($qa));
    }

    /**
     * A Q&A entry needs no ingestion, so it always reports the ingested status.
     *
     * @return array<string, mixed>
     */
    private function qaRow(AgentQuestionAnswer $qa): array
    {
        return [
            'id' => $qa->id,
            'title' => $qa->title,
            'questions' => $this->questionItems($qa->questions),
            'answer' => $qa->answer,
            'status' => SourceStatus::Completed->value,
            'created_at' => $qa->created_at,
            'updated_at' => $qa->updated_at,
        ];
    }

    /**
     * @return list<array{id: string, question: string}>
     */
    private function questionItems(?string $questions): array
    {
        if (empty($questions)) {
            return [];
        }

        return collect(explode("\n", $questions))
            ->map(fn (string $question): string => trim($question))
            ->filter()
            ->values()
            ->map(fn (string $question): array => [
                'id' => md5($question),
                'question' => $question,
            ])
            ->all();
    }
}
