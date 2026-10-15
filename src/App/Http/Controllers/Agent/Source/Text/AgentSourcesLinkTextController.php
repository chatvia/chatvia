<?php

namespace App\Http\Controllers\Agent\Source\Text;

use App\Http\Requests\Agent\Source\LinkSourceTextRequest;
use Domain\Agent\Models\Agent;
use Domain\Knowledge\Source\Actions\AttachSourceToAgentAction;
use Domain\Knowledge\Source\Text\Models\SourceText;
use Illuminate\Http\JsonResponse;

/**
 * Lets the agent use a text the workspace already trained.
 */
class AgentSourcesLinkTextController
{
    public function __invoke(LinkSourceTextRequest $request, Agent $agent, int $textId, AttachSourceToAgentAction $attach): JsonResponse
    {
        // Another workspace's text answers like an unknown id.
        $text = SourceText::query()->where('workspace_id', $agent->workspace_id)->findOrFail($textId);

        abort_if($request->user()->cannot('view', $text), 403);

        if (! $attach->execute($agent, $text)) {
            return response()->json(['message' => __('This text is already attached to the agent.')], 422);
        }

        return response()->json(['message' => __('Text successfully attached to agent.')]);
    }
}
