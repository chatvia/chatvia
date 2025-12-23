<?php

namespace App\Http\Controllers\Agents\ChatLog;

use App\Data\Resources\Agent\ChatLog\ChatLogTopicsResourceData;
use App\Models\Agent;
use Illuminate\Http\Request;

class AgentChatLogTopicsController
{
    public function __invoke(Request $request, Agent $agent)
    {
        abort_if(
            boolean: $request->user()->cannot('view', $agent),
            code: 404,
        );

        abort_if(! has_permission_to_agent($agent, 'agent.chat.view'), 403);

        // Get topics assigned to this agent
        $topics = $agent->topics()
            ->orderBy('chat_topics.topic')
            ->get(['chat_topics.id', 'chat_topics.topic', 'chat_topics.description']);

        return ChatLogTopicsResourceData::fromCollection($topics);
    }
}
