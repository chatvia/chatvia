<?php

namespace App\Http\Controllers\Agents\Topics;

use App\Models\Agent;
use App\Models\ChatTopic;
use Illuminate\Http\Request;

class AgentTopicsUpdateController
{
    public function __invoke(Request $request, Agent $agent, ChatTopic $topic)
    {
        abort_if(
            boolean: $request->user()->cannot('update', $agent),
            code: 404,
        );

        abort_if(! has_permission_to_agent($agent, 'agent.topics.manage'), 403);

        // Check if the topic belongs to this agent
        if (! $agent->topics()->where('chat_topics.id', $topic->id)->exists()) {
            abort(404, 'Topic not found for this agent');
        }

        // Only allow updating user-created topics
        if ($topic->isSystem()) {
            return response()->json([
                'message' => 'System topics cannot be modified',
            ], 403);
        }

        // Ensure the topic belongs to this user
        if ($topic->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'You can only update your own topics',
            ], 403);
        }

        $validated = $request->validate([
            'topic' => [
                'required',
                'string',
                'max:255',
            ],
            'description' => 'required|string|max:500',
        ]);

        // Check if another topic with this name exists for this user
        $existingTopic = ChatTopic::query()
            ->where('user_id', $request->user()->id)
            ->where('topic', $validated['topic'])
            ->where('id', '!=', $topic->id)
            ->first();

        if ($existingTopic) {
            return response()->json([
                'message' => 'You already have a topic with this name',
            ], 422);
        }

        $topic->update($validated);

        return response()->json([
            'message' => 'Topic updated successfully',
            'topic' => $topic,
        ]);
    }
}
