<?php

namespace App\Http\Controllers\Agents\Sources\Qa;

use App\Models\Agent;
use App\Models\AgentQuestionAnswer;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AgentKnowledgeQaShowController
{
    public function __invoke(Request $request, Agent $agent, int $id)
    {
        abort_if(
            boolean: $request->user()->cannot('view', $agent),
            code: 404,
        );

        $qa = AgentQuestionAnswer::where('agent_id', $agent->id)
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

    /**
     * Convert comma-separated questions text to array format for Vue component
     */
    private function parseQuestionsToArray(?string $questions): array
    {
        if (empty($questions)) {
            return [];
        }

        return collect(explode(', ', $questions))
            ->filter()
            ->map(fn ($question) => [
                'id' => Str::uuid()->toString(),
                'question' => trim($question),
            ])
            ->values()
            ->toArray();
    }
}
