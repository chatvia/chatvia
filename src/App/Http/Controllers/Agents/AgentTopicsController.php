<?php

namespace App\Http\Controllers\Agents;

use Domain\Agents\Data\Resources\AgentResourceData;
use Domain\Agents\Models\Agent;
use Domain\Chat\Models\ChatTopic;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AgentTopicsController
{
    public function index(Request $request, Agent $agent): Response|JsonResponse
    {
        abort_if($request->user()->cannot('view', $agent), 404);
        abort_if(! has_permission_to_agent($agent, 'agent.topics.manage'), 403);

        if ($request->wantsJson()) {
            $search = $request->input('search');

            if ($request->boolean('all')) {
                $query = ChatTopic::query()
                    ->forUser($request->user())
                    ->orderBy('topic');

                if ($search) {
                    $query->where('topic', 'ilike', "%{$search}%");
                }

                return response()->json($query->get());
            }

            $query = $agent->topics()->orderBy('chat_topics.topic');

            if ($search) {
                $query->where('chat_topics.topic', 'ilike', "%{$search}%");
            }

            return response()->json($query->get(['chat_topics.*']));
        }

        return Inertia::render('agents/topics/Manage', [
            'agent' => AgentResourceData::fromModel($agent),
        ]);
    }

    public function store(Request $request, Agent $agent): JsonResponse
    {
        abort_if($request->user()->cannot('update', $agent), 404);
        abort_if(! has_permission_to_agent($agent, 'agent.topics.manage'), 403);

        if ($request->has('topic_id')) {
            $validated = $request->validate([
                'topic_id' => ['required', 'integer', 'exists:chat_topics,id'],
            ]);

            $topic = ChatTopic::findOrFail($validated['topic_id']);

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

        $validated = $request->validate([
            'topic' => ['required', 'string', 'max:255'],
            'description' => 'required|string|max:500',
        ]);

        $existing = ChatTopic::query()
            ->where('user_id', $request->user()->id)
            ->where('topic', $validated['topic'])
            ->exists();

        if ($existing) {
            return response()->json([
                'message' => 'You already have a topic with this name',
            ], 422);
        }

        $topic = ChatTopic::create([
            'user_id' => $request->user()->id,
            'topic' => $validated['topic'],
            'description' => $validated['description'],
        ]);

        $agent->topics()->attach($topic->id);

        return response()->json([
            'message' => 'Topic created successfully',
            'topic' => $topic,
        ]);
    }

    public function update(Request $request, Agent $agent, ChatTopic $topic): JsonResponse
    {
        abort_if($request->user()->cannot('update', $agent), 404);
        abort_if(! has_permission_to_agent($agent, 'agent.topics.manage'), 403);

        abort_unless(
            $agent->topics()->where('chat_topics.id', $topic->id)->exists(),
            404,
            'Topic not found for this agent',
        );

        if ($topic->isSystem()) {
            return response()->json(['message' => 'System topics cannot be modified'], 403);
        }

        if ($topic->user_id !== $request->user()->id) {
            return response()->json(['message' => 'You can only update your own topics'], 403);
        }

        $validated = $request->validate([
            'topic' => ['required', 'string', 'max:255'],
            'description' => 'required|string|max:500',
        ]);

        $duplicate = ChatTopic::query()
            ->where('user_id', $request->user()->id)
            ->where('topic', $validated['topic'])
            ->where('id', '!=', $topic->id)
            ->exists();

        if ($duplicate) {
            return response()->json(['message' => 'You already have a topic with this name'], 422);
        }

        $topic->update($validated);

        return response()->json(['message' => 'Topic updated successfully', 'topic' => $topic]);
    }

    public function destroy(Request $request, Agent $agent, ChatTopic $topic): JsonResponse
    {
        abort_if($request->user()->cannot('update', $agent), 404);
        abort_if(! has_permission_to_agent($agent, 'agent.topics.manage'), 403);

        abort_unless(
            $agent->topics()->where('chat_topics.id', $topic->id)->exists(),
            404,
            'Topic not found for this agent',
        );

        $agent->topics()->detach($topic->id);

        if ($topic->isUserDefined() && $topic->agents()->count() === 0) {
            $topic->delete();
        }

        return response()->json(['message' => 'Topic removed successfully']);
    }
}
