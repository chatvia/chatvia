<?php

namespace App\Http\Controllers\Agent\Source\Text;

use App\Http\Requests\Agent\Source\LinkSourceTextRequest;
use Domain\Agent\Models\Agent;
use Domain\Source\Text\Actions\AttachSourceTextToAgentAction;
use Domain\Source\Text\Models\SourceText;
use Illuminate\Http\JsonResponse;

class AgentSourcesLinkTextController
{
    public function __invoke(LinkSourceTextRequest $request, Agent $agent, int $textId, AttachSourceTextToAgentAction $attach): JsonResponse
    {
        $text = SourceText::findOrFail($textId);

        abort_if($request->user()->cannot('view', $text), 403);

        if (! $attach->execute($agent, $text)) {
            return response()->json(['message' => 'This text is already attached to the agent.'], 422);
        }

        return response()->json(['message' => 'Text successfully attached to agent.']);
    }
}
