<?php

namespace App\Http\Controllers\Agent\ChatLog;

use Domain\Agent\Data\Resources\ChatLog\Topics\ChatLogTopicsResourceData;
use Domain\Agent\Models\Agent;
use Illuminate\Http\Request;

/**
 * Topic catalog for an agent's chat log — used to populate the topic filter on
 * the chat-log page.
 */
class TopicController
{
    public function __invoke(Request $request, Agent $agent): ChatLogTopicsResourceData
    {
        abort_if($request->user()->cannot('view', $agent), 404);

        $topics = $agent->topics()
            ->orderBy('chat_topics.topic')
            ->get(['chat_topics.id', 'chat_topics.topic', 'chat_topics.description']);

        return ChatLogTopicsResourceData::fromCollection($topics);
    }
}
