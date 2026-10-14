<?php

declare(strict_types=1);

namespace App\Http\Controllers\Widget\Conversations;

use App\Http\Controllers\Widget\Concerns\InteractsWithWidgetRequest;
use App\Http\Widget\WidgetAgent;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Actions\Conversations\GenerateConversationTitleAction;
use Domain\Conversation\Data\Memory\ChatScopeData;
use Domain\Conversation\Data\Memory\ChatViewerData;
use Domain\Conversation\Services\Memory\ChatMemory;
use Domain\Widget\Services\WidgetVerification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Names a widget conversation (X-CONVERSATION-ID) from its first exchange,
 * at once: the widget calls it right after the first reply, as the hosted
 * chat does, so the title is there when the visitor opens the history. One
 * short LLM call, in the request. Idempotent: a conversation with a title
 * keeps it.
 */
class ConversationTitleController
{
    use InteractsWithWidgetRequest;

    public function __invoke(
        Request $request,
        #[WidgetAgent] Agent $agent,
        ChatMemory $memory,
        WidgetVerification $verification,
        GenerateConversationTitleAction $generateTitle,
    ): JsonResponse {
        $sessionId = $this->widgetSessionId($request);
        $conversationId = $this->widgetConversationId($request);

        if ($sessionId === null || $conversationId === null) {
            return response()->json(['error' => 'Missing thread/session ID'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $viewer = $this->widgetViewer($request);

        // On a protected agent only the chatter who owns the session may use it.
        if ($viewer instanceof ChatViewerData && ! $verification->verifySessionExternalId($sessionId, $agent, $viewer)) {
            return response()->json(['error' => 'Invalid session'], JsonResponse::HTTP_NOT_FOUND);
        }

        // The conversation must belong to this agent (and the signed-in
        // chatter), and to this session: another visitor's id is refused.
        $conversation = $verification->verifyConversationId($conversationId, $agent, $viewer);
        $scope = new ChatScopeData($agent, $sessionId, $conversationId, $viewer);

        if ($conversation === null || ! $memory->withScope($scope)->conversationExists()) {
            return response()->json(['error' => 'Invalid thread'], JsonResponse::HTTP_NOT_FOUND);
        }

        if ($conversation->needsTitle()) {
            $generateTitle->execute($scope);
            $conversation->refresh();
        }

        return response()->json(['title' => $conversation->needsTitle() ? null : $conversation->title]);
    }
}
