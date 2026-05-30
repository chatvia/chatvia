<?php

namespace App\Http\Controllers\Agents\Sources\Text;

use Domain\Agents\Models\Agent;
use Domain\Sources\Text\Actions\DetachSourceTextFromAgentAction;
use Domain\Sources\Text\Models\SourceText;
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
