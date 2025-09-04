<?php

namespace App\Http\Controllers\Agents\ChatLog;

use App\Models\Agent;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\Lead;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;

class AgentChatLogController
{
    public function __invoke(Request $request, Agent $agent)
    {
        abort_if(
            boolean: $request->user()->cannot('view', $agent),
            code: 404,
        );

        $conversationId = $request->query('conversation_id');

        // Query messages for a specific conversation if provided
        $messages = null;
        if ($conversationId) {
            $messages = ChatMessage::query()
                ->whereHas('conversation.session', fn ($query) => $query->where('agent_id', $agent->id))
                ->where('conversation_id', $conversationId)
                ->orderBy('created_at')
                ->paginate(50)
                ->withQueryString();
        } else {
            // Return empty paginated result when no conversation is selected
            $messages = new LengthAwarePaginator(
                [],
                0,
                50,
                1,
                [
                    'path' => request()->url(),
                    'pageName' => 'page',
                ]
            );
        }

        $firstMessageText = null;
        if ($conversationId && $messages && $messages->isNotEmpty()) {
            $firstMessage = $messages->first();
            if ($firstMessage) {
                $firstMessageText = str($firstMessage->content)->stripTags()->limit(80)->value();
            }
        }

        // Get distinct conversations for this agent ordered by most recent activity
        $conversations = ChatConversation::query()
            ->whereHas('session', fn ($query) => $query->where('agent_id', $agent->id))
            ->withCount('messages')
            ->orderByDesc('updated_at')
            ->limit(100)
            ->get()
            ->map(function ($conversation) {
                return (object) [
                    'conversation_id' => $conversation->id,
                    'last_at' => $conversation->updated_at,
                    'messages_count' => $conversation->messages_count,
                    'first_message_excerpt' => null, // Will be populated below
                    'title' => $conversation->title,
                ];
            });

        // Fetch first (earliest) message per conversation for excerpts
        if ($conversations->isNotEmpty()) {
            $conversationIds = $conversations->pluck('conversation_id')->map(fn ($id) => (string) $id);

            $firstMessagesRaw = ChatMessage::query()
                ->whereIn('conversation_id', $conversationIds)
                ->orderBy('created_at')
                ->get(['conversation_id', 'content', 'created_at']);

            $firstPerConversation = [];
            foreach ($firstMessagesRaw as $message) {
                // Only store the first message per conversation (since we're ordered by created_at)
                if (! isset($firstPerConversation[$message->conversation_id])) {
                    $firstPerConversation[$message->conversation_id] = str($message->content)->stripTags()->limit(80)->value();
                }
            }

            $conversations = $conversations->map(function ($c) use ($firstPerConversation) {
                $c->first_message_excerpt = $firstPerConversation[$c->conversation_id] ?? null;

                return $c;
            });
        }

        // Gather lead presence per conversation
        $leadConversationIds = Lead::query()
            ->where('agent_id', $agent->id)
            ->whereIn('conversation_id', $conversations->pluck('conversation_id'))
            ->pluck('conversation_id');

        $lead = null;
        if ($conversationId) {
            $lead = Lead::query()
                ->where('agent_id', $agent->id)
                ->where('conversation_id', $conversationId)
                ->first();
        }

        return Inertia::render('agents/ChatLog', [
            'agent' => $agent,
            'messages' => $messages,
            'conversations' => $conversations,
            'current_conversation_id' => $conversationId,
            'lead_conversation_ids' => $leadConversationIds,
            'lead' => $lead,
            'conversation_title' => $firstMessageText,
        ]);
    }
}
