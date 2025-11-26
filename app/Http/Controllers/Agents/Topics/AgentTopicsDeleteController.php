<?php

namespace App\Http\Controllers\Agents\Topics;

use App\Models\Agent;
use App\Models\ChatTopic;
use Illuminate\Http\Request;

class AgentTopicsDeleteController
{
    public function __invoke(Request $request, Agent $agent, ChatTopic $topic)
    {
        abort_if(
            boolean: $request->user()->cannot('update', $agent),
            code: 404,
        );

        // Check if the topic belongs to this agent
        if (! $agent->topics()->where('chat_topics.id', $topic->id)->exists()) {
            abort(404, 'Topic not found for this agent');
        }

        // Detach the topic from the agent
        $agent->topics()->detach($topic->id);

        // If no other agents are using this topic, delete it
        if ($topic->agents()->count() === 0) {
            $topic->delete();
        }

        return response()->json([
            'message' => 'Topic deleted successfully',
        ]);
    }
}
