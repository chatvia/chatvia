<?php

namespace App\Http\Controllers\Agents\Topics;

use App\Models\Agent;
use App\Models\ChatTopic;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AgentTopicsStoreController
{
    public function __invoke(Request $request, Agent $agent)
    {
        abort_if(
            boolean: $request->user()->cannot('update', $agent),
            code: 404,
        );

        // Check if adding an existing topic or creating a new one
        if ($request->has('topic_id')) {
            // Associate existing topic with agent
            $validated = $request->validate([
                'topic_id' => [
                    'required',
                    'integer',
                    'exists:chat_topics,id',
                ],
            ]);

            $topic = ChatTopic::findOrFail($validated['topic_id']);

            // Check if already associated
            if ($agent->topics()->where('chat_topics.id', $topic->id)->exists()) {
                return response()->json([
                    'message' => 'Topic already associated with this agent',
                ], 422);
            }

            $agent->topics()->attach($topic->id);

            return response()->json([
                'message' => 'Topic added successfully',
                'topic' => $topic,
            ]);
        }

        // Create new topic and associate with agent
        $validated = $request->validate([
            'topic' => [
                'required',
                'string',
                'max:255',
                Rule::unique('chat_topics', 'topic'),
            ],
            'description' => 'required|string|max:500',
        ]);

        $topic = ChatTopic::create($validated);

        // Associate the topic with the agent
        $agent->topics()->attach($topic->id);

        return response()->json([
            'message' => 'Topic created successfully',
            'topic' => $topic,
        ]);
    }
}
