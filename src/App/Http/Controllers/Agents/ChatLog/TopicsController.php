<?php

namespace App\Http\Controllers\Agents\ChatLog;

use Domain\Agents\Data\Resources\ChatLog\ChatLogTopicsResourceData;
use Domain\Agents\Models\Agent;
use Illuminate\Http\Request;

/**
 * Topic catalog for an agent's chat log — used to populate the topic filter on
 * the chat-log page.
 */
class TopicsController
{
    public function __invoke(Request $request, Agent $agent): ChatLogTopicsResourceData
    {
        abort_if($request->user()->cannot('view', $agent), 404);
        abort_if(! has_permission_to_agent($agent, 'agent.chat.view'), 403);

        $topics = $agent->topics()
            ->orderBy('chat_topics.topic')
            ->get(['chat_topics.id', 'chat_topics.topic', 'chat_topics.description']);

        return ChatLogTopicsResourceData::fromCollection($topics);
    }
}
