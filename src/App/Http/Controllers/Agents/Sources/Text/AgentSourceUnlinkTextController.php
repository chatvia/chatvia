<?php

namespace App\Http\Controllers\Agents\Sources\Text;

use Domain\Agents\Models\Agent;
use Domain\Sources\Text\Actions\DetachSourceTextFromAgentAction;
use Domain\Sources\Text\Models\SourceText;
use Illuminate\Http\Request;

class AgentSourceUnlinkTextController
{
    public function __invoke(Request $request, Agent $agent, int $textId, DetachSourceTextFromAgentAction $detach)
    {
        $user = $request->user();

        /** @var SourceText $text */
        $text = SourceText::find($textId);

        abort_if($user->cannot('view', $text), 403);

        $detach->execute($agent, $text);

        return response()->json(['message' => 'Text successfully unlinked from agent.']);
    }
}
