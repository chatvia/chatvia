<?php

namespace App\Http\Controllers\Widget\Activity;

use App\Http\Controllers\Widget\Concerns\InteractsWithWidgetRequest;
use App\Http\Widget\WidgetAgent;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Actions\Conversations\FindWidgetConversationAction;
use Domain\Conversation\Models\Conversation;
use Domain\Widget\Data\Auth\WidgetAckData;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Periodic "still here" ping from an open widget. Moves the conversation's
 * last-activity time forward so it counts as live in the operator inbox. A
 * hidden (never used) conversation is not stamped.
 */
readonly class HeartbeatController
{
    use InteractsWithWidgetRequest;

    public function __invoke(
        Request $request,
        #[WidgetAgent] Agent $agent,
        FindWidgetConversationAction $findConversation,
    ): JsonResponse|WidgetAckData {
        $sessionId = $this->widgetSessionId($request);
        $conversationId = $request->input('conversation_id') ?? $this->widgetConversationId($request);

        if ($sessionId === null) {
            return response()->json(['error' => 'Missing session'], JsonResponse::HTTP_BAD_REQUEST);
        }

        if (! $conversationId) {
            return new WidgetAckData;
        }

        // The conversation must belong to this session and agent, or anyone
        // with a guessed id could move another visitor's activity time.
        $conversation = $findConversation->execute((string) $conversationId, $sessionId, $agent, $this->widgetViewer($request));

        if (! $conversation instanceof Conversation) {
            return response()->json(['error' => 'Conversation not found'], JsonResponse::HTTP_NOT_FOUND);
        }

        // A base-query update, so `updated_at` (the chat log's sort key) is
        // not touched. Only a visible conversation counts as live, and the
        // stamp moves at most once a minute: every open widget pings.
        Conversation::query()
            ->whereKey($conversation->getKey())
            ->where('is_visible', true)
            ->where(fn (Builder $query) => $query
                ->whereNull('last_activity_at')
                ->orWhere('last_activity_at', '<', now()->subMinute()))
            ->toBase()
            ->update(['last_activity_at' => now()]);

        return new WidgetAckData;
    }
}
