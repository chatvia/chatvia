<?php

namespace App\Http\Controllers\Agents\Sources\Qa;

use App\Models\Agent;
use App\Models\AgentQuestionAnswer;
use Illuminate\Http\Request;

class AgentKnowledgeQaUpdateController
{
    public function __invoke(Request $request, Agent $agent, int $id)
    {
        abort_if(
            boolean: $request->user()->cannot('view', $agent),
            code: 404,
        );

        abort_if(
            boolean: $request->user()->cannot('update', $agent),
            code: 404,
        );

        $qa = AgentQuestionAnswer::where('agent_id', $agent->id)
            ->findOrFail($id);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'questions' => ['required', 'array', 'min:1'],
            'questions.*.question' => ['required', 'string'],
            'answer' => ['required', 'string', 'min:10'],
        ]);

        // Extract question texts and store as comma-separated text
        $questionsText = collect($validated['questions'])
            ->pluck('question')
            ->filter()
            ->implode(', ');

        $qa->update([
            'title' => $validated['title'],
            'questions' => $questionsText,
            'answer' => $validated['answer'],
        ]);

        return back();
    }
}
