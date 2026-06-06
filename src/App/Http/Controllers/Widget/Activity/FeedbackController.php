<?php

namespace App\Http\Controllers\Widget\Activity;

use Domain\Conversation\Services\Memory\ChatMemory;
use Domain\Widget\Data\Auth\WidgetAckData;
use Domain\Widget\Services\WidgetVerification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FeedbackController
{
    public function __invoke(Request $request, ChatMemory $memory, WidgetVerification $widgetVerification): JsonResponse|WidgetAckData
    {
        $agentId = $request->header('X-AGENT-ID');

        if (! $widgetVerification->verifyByAgentExternalId($agentId, $request)) {
            return response()->json(['error' => 'Domain not allowed'], 403);
        }

        $memory
            ->withContext(current_agent_from_widget(), $request->header('X-SESSION-ID'), $request->header('X-CONVERSATION-ID'))
            ->feedback($request->header('X-MESSAGE-ID'), $request->boolean('feedback') ? 'positive' : 'negative');

        return new WidgetAckData;
    }
}
