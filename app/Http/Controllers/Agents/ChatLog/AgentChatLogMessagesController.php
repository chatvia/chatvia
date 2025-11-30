<?php

namespace App\Http\Controllers\Agents\ChatLog;

use App\Data\Resources\Agent\ChatLog\ChatLogMessagesResourceData;
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

        $messages = ChatMessage::query()
            ->with('llm')
            ->forAgentConversation($agent->id, $conversationId)
            ->orderBy('created_at')
            ->orderByRaw("CASE WHEN role = 'user' THEN 0 ELSE 1 END")
            ->paginate(50, page: $request->integer('page', 1));

        $lead = Lead::query()
            ->where('agent_id', $agent->id)
            ->where('chat_conversation_id', $conversationId)
            ->first();

        return ChatLogMessagesResourceData::fromPaginator($messages, $lead);
    }
}
