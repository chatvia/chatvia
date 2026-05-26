<?php

namespace App\Http\Controllers\Agents\Sources\Text;

use Domain\Agents\Models\Agent;
use Domain\Sources\Text\Actions\AttachSourceTextToAgentAction;
use Domain\Sources\Text\Models\SourceText;
use Illuminate\Http\Request;

class AgentSourcesLinkTextController
{
    public function __invoke(Request $request, Agent $agent, int $textId, AttachSourceTextToAgentAction $attach)
    {
        $request->validate(['text_id' => ['required', 'exists:source_texts,id']]);

        $user = $request->user();

        /** @var SourceText $text */
        $text = SourceText::find($textId);

        abort_if($user->cannot('view', $text), 403);

        if (! $attach->execute($agent, $text)) {
            return response()->json(['message' => 'This text is already attached to the agent.'], 422);
        }

        return response()->json(['message' => 'Text successfully attached to agent.']);
    }
}
