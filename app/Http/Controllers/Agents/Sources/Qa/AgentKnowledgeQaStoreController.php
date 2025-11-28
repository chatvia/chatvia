<?php

namespace App\Http\Controllers\Agents\Sources\Qa;

use App\Models\Agent;
use Illuminate\Http\Request;

class AgentKnowledgeQaStoreController
{
    public function __invoke(Request $request, Agent $agent)
    {
        abort_if(
            boolean: $request->user()->cannot('view', $agent),
            code: 404,
        );

        abort_if(
            boolean: $request->user()->cannot('update', $agent),
            code: 404,
        );

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'questions' => ['required', 'array', 'min:1'],
            'questions.*.question' => ['required', 'string'],
            'answer' => ['required', 'string', 'min:10'],
        ]);

        // TODO: Implement Q&A storage logic
        // This will be implemented by the backend team

        return response()->json([
            'message' => 'Q&A entry created successfully',
        ]);
    }
}
