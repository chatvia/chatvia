<?php

namespace App\Http\Controllers\Agents\ChatLog;

use App\Data\Resources\Agent\ChatLog\ChatLogMessagesResourceData;
use App\Models\Agent;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\Lead;
use Illuminate\Http\Request;

class AgentChatLogMessagesController
{
    public function __invoke(Request $request, Agent $agent, ChatConversation $conversation)
    {
        abort_if(
            boolean: $request->user()->cannot('view', $agent),
            code: 404,
        );

        abort_if(! has_permission_to_agent($agent, 'agent.chat.view'), 403);

        // Verify conversation belongs to this agent
        if ($conversation->session?->agent_id !== $agent->id) {
            abort(404);
        }

        $messages = ChatMessage::query()
            ->with('llm')
            ->forAgentConversation($agent->id, $conversation->id)
            ->orderBy('created_at')
            ->orderByRaw("CASE WHEN role = 'user' THEN 0 ELSE 1 END")
            ->paginate(50, page: $request->integer('page', 1));

        $lead = Lead::query()
            ->where('agent_id', $agent->id)
            ->where('chat_conversation_id', $conversation->id)
            ->first();

        // Load handoff info if not already loaded
        $conversation->loadMissing('handoffUser');

        $response = ChatLogMessagesResourceData::fromPaginator($messages, $lead);

        // Convert to array and add handoff info
        $data = $response->toArray();
        $data['handoff'] = [
            'status' => $conversation?->handoff_status ?? 'ai',
            'admin_id' => $conversation?->handoff_user_id,
            'admin_name' => $conversation?->handoffUser?->name,
            'handoff_at' => $conversation?->handoff_at?->toIso8601String(),
        ];

        return response()->json($data);
    }
}
