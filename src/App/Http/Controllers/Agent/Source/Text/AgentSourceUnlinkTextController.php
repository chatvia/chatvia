<?php

namespace App\Http\Controllers\Agent\Source\Text;

use Domain\Agent\Models\Agent;
use Domain\Source\Text\Actions\DetachSourceTextFromAgentAction;
use Domain\Source\Text\Models\SourceText;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgentSourceUnlinkTextController
{
    public function __invoke(Request $request, Agent $agent, int $textId, DetachSourceTextFromAgentAction $detach): JsonResponse
    {
        $text = SourceText::findOrFail($textId);

        abort_if($request->user()->cannot('view', $text), 403);

        $detach->execute($agent, $text);

        return response()->json(['message' => 'Text successfully unlinked from agent.']);
    }
}
