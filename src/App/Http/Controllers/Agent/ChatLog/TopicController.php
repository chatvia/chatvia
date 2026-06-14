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
        $topics = $agent->topics()
            ->orderBy('topics.topic')
            ->get(['topics.id', 'topics.topic', 'topics.description']);

        return ChatLogTopicsResourceData::fromCollection($topics);
    }
}
