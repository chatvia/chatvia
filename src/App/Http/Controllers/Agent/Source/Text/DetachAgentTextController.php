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
    public function __invoke(Request $request, Agent $agent, int $id, DetachSourceFromAgentAction $detach): JsonResponse
    {
        /** @var SourceText $text */
        $text = $agent->texts()->where('source_texts.id', $id)->firstOrFail();

        abort_if($request->user()->cannot('delete', $text), 403);

        $detach->execute($agent, $text);

        return response()->json(['message' => __('Text unlinked from agent successfully.')]);
    }
}
