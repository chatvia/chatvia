<?php

namespace App\Http\Controllers\Agents\ChatLog;

use App\Models\Agent;
use App\Models\ChatMessage;
use App\Models\Lead;
use Illuminate\Http\Request;

class AgentChatLogMessagesController
{
    public function __invoke(Request $request, Agent $agent, string $conversationId)
    {
        abort_if(
            boolean: $request->user()->cannot('view', $agent),
            code: 404,
        );

        $page = $request->query('page', 1);

        // Query messages for the conversation
        $messages = ChatMessage::query()
            ->whereHas('conversation.session', fn ($query) => $query->where('agent_id', $agent->id))
            ->where('chat_conversation_id', $conversationId)
            ->orderBy('created_at')
            ->paginate(50, page: $page);

        $firstMessageText = null;
        if ($messages->isNotEmpty()) {
            $firstMessage = $messages->first();
            if ($firstMessage) {
                $firstMessageText = str($firstMessage->content)->stripTags()->limit(80)->value();
            }
        }

        $lead = Lead::query()
            ->where('agent_id', $agent->id)
            ->where('chat_conversation_id', $conversationId)
            ->first();

        return response()->json([
            'messages' => $messages,
            'conversation_title' => $firstMessageText,
            'lead' => $lead,
        ]);
    }
}
