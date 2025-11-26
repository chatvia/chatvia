<?php

namespace App\Http\Controllers\Agents\Topics;

use App\Models\Agent;
use App\Models\ChatTopic;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AgentTopicsUpdateController
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

        $validated = $request->validate([
            'topic' => [
                'required',
                'string',
                'max:255',
                Rule::unique('chat_topics', 'topic')->ignore($topic->id),
            ],
            'description' => 'required|string|max:500',
        ]);

        $topic->update($validated);

        return response()->json([
            'message' => 'Topic updated successfully',
            'topic' => $topic,
        ]);
    }
}
