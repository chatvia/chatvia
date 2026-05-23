<?php

namespace App\Http\Controllers\Agents\Sources;

use App\Data\Resources\Agent\AgentResourceData;
use App\Models\Agent;
use App\Models\AgentQuestionAnswer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class AgentQaController
{
    public function index(Request $request, Agent $agent): Response|JsonResponse
    {
        abort_if($request->user()->cannot('view', $agent), 404);
        abort_if(! has_permission_to_agent($agent, 'agent.sources.train.qa'), 403);

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
            'status' => 'ingested',
            'created_at' => $qa->created_at,
            'updated_at' => $qa->updated_at,
        ]);
    }

    public function store(Request $request, Agent $agent): RedirectResponse
    {
        abort_if($request->user()->cannot('view', $agent), 404);
        abort_if($request->user()->cannot('update', $agent), 404);
        abort_if(! has_permission_to_agent($agent, 'agent.sources.train.qa'), 403);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'questions' => ['required', 'array', 'min:1'],
            'questions.*.question' => ['required', 'string'],
            'answer' => ['required', 'string', 'min:10'],
        ]);

        AgentQuestionAnswer::create([
            'agent_id' => $agent->id,
            'title' => $validated['title'],
            'questions' => $this->flattenQuestions($validated['questions']),
            'answer' => $validated['answer'],
        ]);

        return back();
    }

    public function update(Request $request, Agent $agent, int $id): RedirectResponse
    {
        abort_if($request->user()->cannot('view', $agent), 404);
        abort_if($request->user()->cannot('update', $agent), 404);

        $qa = AgentQuestionAnswer::query()
            ->where('agent_id', $agent->id)
            ->findOrFail($id);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'questions' => ['required', 'array', 'min:1'],
            'questions.*.question' => ['required', 'string'],
            'answer' => ['required', 'string', 'min:10'],
        ]);

        $qa->update([
            'title' => $validated['title'],
            'questions' => $this->flattenQuestions($validated['questions']),
            'answer' => $validated['answer'],
        ]);

        return back();
    }

    public function destroy(Request $request, Agent $agent, int $id): JsonResponse
    {
        abort_if($request->user()->cannot('update', $agent), 404);
        abort_if(! has_permission_to_agent($agent, 'agent.sources.delete'), 403);

        $qa = AgentQuestionAnswer::query()
            ->where('agent_id', $agent->id)
            ->findOrFail($id);

        $qa->delete();

        return response()->json(['message' => 'Q&A entry deleted successfully']);
    }

    public function unlink(Request $request, Agent $agent, int $id): JsonResponse
    {
        abort_if($request->user()->cannot('view', $agent), 404);
        abort_if($request->user()->cannot('update', $agent), 404);

        // TODO: Implement Q&A unlink logic
        // This will be implemented by the backend team

        return response()->json(['message' => 'Q&A entry unlinked successfully']);
    }

    private function buildQaList(Agent $agent): \Illuminate\Support\Collection
    {
        return AgentQuestionAnswer::query()
            ->where('agent_id', $agent->id)
            ->latest()
            ->get()
            ->map(fn (AgentQuestionAnswer $qa) => [
                'id' => $qa->id,
                'title' => $qa->title,
                'questions' => $this->parseQuestionsToArray($qa->questions),
                'answer' => $qa->answer,
                'status' => 'ingested',
                'created_at' => $qa->created_at,
                'updated_at' => $qa->updated_at,
            ]);
    }

    private function parseQuestionsToArray(?string $questions): array
    {
        if (empty($questions)) {
            return [];
        }

        return collect(explode(', ', $questions))
            ->filter()
            ->map(fn (string $question) => [
                'id' => Str::uuid()->toString(),
                'question' => trim($question),
            ])
            ->values()
            ->toArray();
    }

    /**
     * Collapse the array-of-{question} payload into a comma-separated string.
     */
    private function flattenQuestions(array $questions): string
    {
        return collect($questions)
            ->pluck('question')
            ->filter()
            ->implode(', ');
    }
}
