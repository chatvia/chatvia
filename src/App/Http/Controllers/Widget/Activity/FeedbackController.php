<?php

namespace App\Http\Controllers\Widget\Activity;

use App\Widget\WidgetVerification;
use Domain\Chat\Services\Memory\ChatMemory;
use Domain\Widget\Data\Auth\WidgetAckData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FeedbackController
{
    public function __invoke(Request $request, ChatMemory $memory, WidgetVerification $widgetVerification): JsonResponse|WidgetAckData
    {
        $agentId = $request->header('X-AGENT-ID');

        if (! $widgetVerification->verifyByAgentExternalId($agentId, $request)) {
            return response()->json(['error' => 'Could not verify the agent'], 403);
        }

        $memory
            ->setAgent(current_agent_from_widget())
            ->setExternalSessionId($request->header('X-SESSION-ID'))
            ->setConversationId($request->header('X-THREAD-ID'))
            ->feedback($request->header('X-MESSAGE-ID'), $request->boolean('feedback') ? 'positive' : 'negative');

        return new WidgetAckData;
    }
}
