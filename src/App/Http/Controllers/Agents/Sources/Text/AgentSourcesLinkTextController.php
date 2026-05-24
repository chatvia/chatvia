<?php

namespace App\Http\Controllers\Agents\Sources\Text;

use Domain\Agents\Models\Agent;
use Domain\Sources\Text\Models\SourceText;
use Illuminate\Http\Request;

class AgentSourcesLinkTextController
{
    public function __invoke(Request $request, Agent $agent, int $textId)
    {
        $request->validate([
            'text_id' => ['required', 'exists:source_texts,id'],
        ]);

        $user = $request->user();

        /** @var SourceText $text */
        $text = SourceText::find($textId);

        abort_if(
            boolean: $user->cannot('view', $text),
            code: 403,
        );

        // Check if text is already attached to this agent
        if ($agent->texts()->where('source_texts.id', $text->id)->exists()) {
            return response()->json([
                'message' => 'This text is already attached to the agent.',
            ], 422);
        }

        $agent->texts()->attach($text->id);

        return response()->json([
            'message' => 'Text successfully attached to agent.',
        ]);
    }
}
