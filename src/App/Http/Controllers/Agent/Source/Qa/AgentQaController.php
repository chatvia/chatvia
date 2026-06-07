<?php

namespace App\Http\Controllers\Agent\Source\Qa;

use App\Http\Requests\Agent\Source\Qa\CreateAgentQaRequest;
use App\Http\Requests\Agent\Source\Qa\UpdateAgentQaRequest;
use Domain\Agent\Data\Resources\Account\AgentResourceData;
use Domain\Agent\Models\Agent;
use Domain\Knowledge\Source\Enums\SourceStatus;
use Domain\Knowledge\Source\QA\Actions\CreateAgentQaAction;
use Domain\Knowledge\Source\QA\Actions\UpdateAgentQaAction;
use Domain\Knowledge\Source\QA\Models\AgentQuestionAnswer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class AgentQaController
{
    public function index(Request $request, Agent $agent): Response|JsonResponse
    {
        abort_if($request->user()->cannot('view', $agent), 404);

        if ($request->wantsJson()) {
            return response()->json(['data' => $this->buildQaList($agent)]);
        }

        return Inertia::render('agents/Sources/Qa', [
            'agent' => AgentResourceData::fromModel($agent),
        ]);
    }

    public function show(Request $request, Agent $agent, int $id): JsonResponse
    {
        abort_if($request->user()->cannot('view', $agent), 404);

        $qa = AgentQuestionAnswer::query()
            ->where('agent_id', $agent->id)
            ->findOrFail($id);

        return response()->json([
            'id' => $qa->id,
            'title' => $qa->title,
            'questions' => $this->parseQuestionsToArray($qa->questions),
            'answer' => $qa->answer,
            'status' => SourceStatus::Completed->value,
            'created_at' => $qa->created_at,
            'updated_at' => $qa->updated_at,
        ]);
    }

    public function store(CreateAgentQaRequest $request, Agent $agent, CreateAgentQaAction $createQa): RedirectResponse
    {
        $createQa->execute(
            agent: $agent,
            title: (string) $request->validated('title'),
            questions: (array) $request->validated('questions'),
            answer: (string) $request->validated('answer'),
        );

        return back();
    }

    public function update(UpdateAgentQaRequest $request, Agent $agent, int $id, UpdateAgentQaAction $updateQa): RedirectResponse
    {
        $qa = AgentQuestionAnswer::query()
            ->where('agent_id', $agent->id)
            ->findOrFail($id);

        $updateQa->execute(
            qa: $qa,
            title: (string) $request->validated('title'),
            questions: (array) $request->validated('questions'),
            answer: (string) $request->validated('answer'),
        );

        return back();
    }

    public function destroy(Request $request, Agent $agent, int $id): JsonResponse
    {
        abort_if($request->user()->cannot('update', $agent), 404);

        $qa = AgentQuestionAnswer::query()
            ->where('agent_id', $agent->id)
            ->findOrFail($id);

        $qa->delete();

        return response()->json(['message' => 'Q&A entry deleted successfully']);
    }

    private function buildQaList(Agent $agent): Collection
    {
        return AgentQuestionAnswer::query()
            ->where('agent_id', $agent->id)
            ->latest()
            ->get()
            ->map(fn (AgentQuestionAnswer $qa): array => [
                'id' => $qa->id,
                'title' => $qa->title,
                'questions' => $this->parseQuestionsToArray($qa->questions),
                'answer' => $qa->answer,
                'status' => SourceStatus::Completed->value,
                'created_at' => $qa->created_at,
                'updated_at' => $qa->updated_at,
            ]);
    }

    private function parseQuestionsToArray(?string $questions): array
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
            ->toArray();
    }
}
