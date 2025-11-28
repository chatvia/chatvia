<?php

namespace App\Http\Controllers\Agents\ChatLog;

use App\Data\Resources\Agent\AgentResourceData;
use App\Models\Agent;
use Illuminate\Http\Request;
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

        return Inertia::render('agents/ChatLog', [
            'agent' => AgentResourceData::fromModel($agent),
            'current_conversation_id' => $conversationId,
        ]);
    }
}
