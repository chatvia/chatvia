<?php

namespace App\Http\Controllers\Agents\ChatLog;

use App\Data\Resources\Agent\ChatLog\ChatLogConversationsResourceData;
use App\Models\Agent;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\Lead;
use Illuminate\Http\Request;

class AgentChatLogConversationsController
{
    public function __invoke(Request $request, Agent $agent)
    {
        abort_if(
            boolean: $request->user()->cannot('view', $agent),
            code: 404,
        );

        $conversations = ChatConversation::query()
            ->forAgent($agent->id)
            ->withSessionAndTopic()
            ->filterByTopic($request->query('topic'))
            ->filterBySentiment($request->query('sentiment'))
            ->filterByEmotion($request->query('emotion'))
            ->withCount('messages')
            ->orderByLatestActivity()
            ->paginate(10, pageName: 'conversations', page: $request->integer('page', 1));

        $excerpts = $this->getFirstMessageExcerpts($conversations->pluck('id'));
        $leadConversationIds = $this->getLeadConversationIds($agent);

        return ChatLogConversationsResourceData::fromPaginator(
            conversations: $conversations,
            excerpts: $excerpts,
            leadConversationIds: $leadConversationIds,
        );
    }

    private function getFirstMessageExcerpts($conversationIds)
    {
        if ($conversationIds->isEmpty()) {
            return collect();
        }

        $firstMessagesRaw = ChatMessage::query()
            ->whereIn('chat_conversation_id', $conversationIds)
            ->orderBy('created_at')
            ->get(['chat_conversation_id', 'content', 'created_at']);

        $firstPerConversation = collect();
        foreach ($firstMessagesRaw as $message) {
            if (! $firstPerConversation->has($message->chat_conversation_id)) {
                $firstPerConversation->put(
                    $message->chat_conversation_id,
                    str($message->content)->stripTags()->limit(80)->value()
                );
            }
        }

        return $firstPerConversation;
    }

    private function getLeadConversationIds(Agent $agent)
    {
        $allConversationIds = ChatConversation::query()
            ->forAgent($agent->id)
            ->pluck('id');

        return Lead::query()
            ->where('agent_id', $agent->id)
            ->whereIn('chat_conversation_id', $allConversationIds)
            ->pluck('chat_conversation_id');
    }
}
