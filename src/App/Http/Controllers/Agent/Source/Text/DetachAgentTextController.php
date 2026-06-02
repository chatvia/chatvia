<?php

namespace App\Http\Controllers\Agent\Source\Text;

use Domain\Agent\Models\Agent;
use Domain\Source\Text\Actions\DetachSourceTextFromAgentAction;
use Domain\Source\Text\Models\SourceText;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Remove a text source from this agent without deleting the underlying
 * SourceText (which may be shared with other agents).
 */
class DetachAgentTextController
{
    public function __invoke(Request $request, Agent $agent, int $id, DetachSourceTextFromAgentAction $detach): JsonResponse
    {
        abort_if($request->user()->cannot('view', $agent), 404);

        /** @var SourceText $text */
        $text = $agent->texts()->where('source_texts.id', $id)->firstOrFail();

        abort_if($request->user()->cannot('delete', $text), 403);

        $detach->execute($agent, $text);

        return response()->json(['message' => 'Text unlinked from agent successfully.']);
    }
}
