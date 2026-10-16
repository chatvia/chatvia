<?php

namespace App\Http\Controllers\Agent\Source\Text;

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Source\Actions\DetachSourceFromAgentAction;
use Domain\Knowledge\Source\Text\Models\SourceText;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Remove a text source from this agent without deleting the underlying
 * SourceText (which may be shared with other agents).
 */
class DetachAgentTextController
{
    public function __invoke(Request $request, Agent $agent, SourceText $text, DetachSourceFromAgentAction $detach): JsonResponse
    {
        // Texts are linked through `agent_sources`, which `can.access.agent`
        // does not cover — a workspace text not attached to this agent is a 404.
        abort_unless($agent->texts()->whereKey($text->id)->exists(), 404);

        abort_if($request->user()->cannot('delete', $text), 403);

        $detach->execute($agent, $text);

        return response()->json(['message' => __('Text unlinked from agent successfully.')]);
    }
}
