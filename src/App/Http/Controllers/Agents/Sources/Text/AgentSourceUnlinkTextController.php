<?php

namespace App\Http\Controllers\Agents\Sources\Text;

use Domain\Agents\Models\Agent;
use Domain\Sources\Text\Models\SourceText;
use Illuminate\Http\Request;

class AgentSourceUnlinkTextController
{
    public function __invoke(Request $request, Agent $agent, int $textId)
    {
        $user = $request->user();

        /** @var SourceText $text */
        $text = SourceText::find($textId);

        abort_if(
            boolean: $user->cannot('view', $text),
            code: 403,
        );

        // Detach the text from the agent
        $agent->texts()->detach($text->id);

        return response()->json([
            'message' => 'Text successfully unlinked from agent.',
        ]);
    }
}
