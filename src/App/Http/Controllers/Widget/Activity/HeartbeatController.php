<?php

namespace App\Http\Controllers\Widget\Activity;

use App\Http\Controllers\Widget\Concerns\InteractsWithWidgetRequest;
use App\Http\Widget\WidgetAgent;
use Domain\Agent\Models\Agent;
use Domain\Widget\Actions\RecordWidgetHeartbeatAction;
use Domain\Widget\Data\Auth\WidgetAckData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Periodic "still here" ping from an open widget. Moves the conversation's
 * last-activity time forward so it counts as live in the operator inbox. A
 * hidden (never used) conversation is not stamped, and most pings end before
 * the database (see {@see RecordWidgetHeartbeatAction}).
 */
readonly class HeartbeatController
{
    use InteractsWithWidgetRequest;

    public function __invoke(
        Request $request,
        #[WidgetAgent] Agent $agent,
        RecordWidgetHeartbeatAction $heartbeat,
    ): JsonResponse|WidgetAckData {
        $sessionId = $this->widgetSessionId($request);
        $conversationId = $request->input('conversation_id') ?? $this->widgetConversationId($request);

        if ($sessionId === null) {
            return response()->json(['error' => 'Missing session'], JsonResponse::HTTP_BAD_REQUEST);
        }

        if (! $conversationId) {
            return new WidgetAckData;
        }

        if (! $heartbeat->execute($agent, (string) $conversationId, $sessionId, $this->widgetViewer($request))) {
            return response()->json(['error' => 'Conversation not found'], JsonResponse::HTTP_NOT_FOUND);
        }

        return new WidgetAckData;
    }
}
