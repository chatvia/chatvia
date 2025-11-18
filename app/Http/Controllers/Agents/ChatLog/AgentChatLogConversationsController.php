<?php

namespace App\Http\Controllers\Agents\ChatLog;

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

        $page = $request->query('page', 1);

        // Get distinct conversations for this agent ordered by most recent activity
        $conversationsPaginator = ChatConversation::query()
            ->with('session')
            ->whereHas('session', fn ($query) => $query->where('agent_id', $agent->id))
            ->withCount('messages')
            ->orderByDesc('updated_at')
            ->paginate(10, pageName: 'conversations', page: $page);

        // Transform the paginated data
        $conversationsPaginator->through(function ($conversation) {
            return [
                'conversation_id' => $conversation->id,
                'last_at' => $conversation->updated_at,
                'messages_count' => $conversation->messages_count,
                'first_message_excerpt' => null, // Will be populated below
                'title' => $conversation->title,
                'country' => $conversation->session?->country,
                'user_agent' => $conversation->session?->user_agent,
            ];
        });

        // Fetch first (earliest) message per conversation for excerpts
        if ($conversationsPaginator->isNotEmpty()) {
            $conversationIds = $conversationsPaginator->pluck('conversation_id')->map(fn ($id) => (string) $id);

            $firstMessagesRaw = ChatMessage::query()
                ->whereIn('chat_conversation_id', $conversationIds)
                ->orderBy('created_at')
                ->get(['chat_conversation_id', 'content', 'created_at']);

            $firstPerConversation = [];
            foreach ($firstMessagesRaw as $message) {
                // Only store the first message per conversation (since we're ordered by created_at)
                if (! isset($firstPerConversation[$message->chat_conversation_id])) {
                    $firstPerConversation[$message->chat_conversation_id] = str($message->content)->stripTags()->limit(80)->value();
                }
            }

            $conversationsPaginator->through(function ($c) use ($firstPerConversation) {
                $c['first_message_excerpt'] = $firstPerConversation[$c['conversation_id']] ?? null;

                return $c;
            });
        }

        // Gather lead presence per conversation for all conversations (not just current page)
        $allConversationIds = ChatConversation::query()
            ->whereHas('session', fn ($query) => $query->where('agent_id', $agent->id))
            ->pluck('id');

        $leadConversationIds = Lead::query()
            ->where('agent_id', $agent->id)
            ->whereIn('chat_conversation_id', $allConversationIds)
            ->pluck('chat_conversation_id');

        return response()->json([
            'conversations' => $conversationsPaginator,
            'lead_conversation_ids' => $leadConversationIds,
        ]);
    }
}
