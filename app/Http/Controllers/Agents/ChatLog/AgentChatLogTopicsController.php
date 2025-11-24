<?php

namespace App\Http\Controllers\Agents\ChatLog;

use App\Models\Agent;
use App\Models\ChatTopic;
use Illuminate\Http\Request;

class AgentChatLogTopicsController
{
    public function __invoke(Request $request, Agent $agent)
    {
        abort_if(
            boolean: $request->user()->cannot('view', $agent),
            code: 404,
        );

        $topics = ChatTopic::query()
            ->orderBy('topic')
            ->get(['id', 'topic', 'description']);

        return response()->json([
            'topics' => $topics,
        ]);
    }
}
