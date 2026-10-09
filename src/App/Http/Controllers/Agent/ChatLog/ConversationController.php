<?php

namespace App\Http\Controllers\Agent\ChatLog;

use App\Http\Requests\Agent\ChatLog\ListChatLogConversationsRequest;
use Domain\Agent\Actions\ChatLog\ListChatLogConversationsAction;
use Domain\Agent\Data\Resources\ChatLog\Conversations\ChatLogConversationsResourceData;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Actions\Removal\AdminDeleteConversationAction;
use Domain\Conversation\Models\Conversation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * List and delete conversations in an agent's chat log. Listing supports the
 * full filter set (topic / sentiment / tone / date / country / source / …)
 * driving the chat-log page.
 */
class ConversationController
{
    public function index(ListChatLogConversationsRequest $request, Agent $agent, ListChatLogConversationsAction $list): ChatLogConversationsResourceData
    {
        return $list->execute($agent, $request->toData());
    }

    public function destroy(
        Request $request,
        Agent $agent,
        string $conversationId,
        AdminDeleteConversationAction $deleteConversation,
    ): JsonResponse {
        $conversation = Conversation::query()
            ->forAgent($agent->id)
            ->where('external_id', $conversationId)
            ->firstOrFail();

        $deleteConversation->execute($agent, $conversation, $request->user());

        return response()->json(['success' => true, 'message' => __('Conversation deleted successfully.')]);
    }
}
