<?php

namespace App\Http\Controllers\Agents\Topics;

use Domain\Agents\Actions\Topics\AttachExistingTopicToAgentAction;
use Domain\Agents\Actions\Topics\CreateAgentTopicAction;
use Domain\Agents\Actions\Topics\DetachTopicFromAgentAction;
use Domain\Agents\Actions\Topics\UpdateChatTopicAction;
use Domain\Agents\Data\Resources\Account\AgentResourceData;
use Domain\Agents\Models\Agent;
use Domain\Chat\Models\ChatTopic;
use Domain\Teams\Exceptions\InviteRejectedException;
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

    public function store(
        Request $request,
        Agent $agent,
        AttachExistingTopicToAgentAction $attach,
        CreateAgentTopicAction $createTopic,
    ): JsonResponse {
        abort_if($request->user()->cannot('update', $agent), 404);
        abort_if(! has_permission_to_agent($agent, 'agent.topics.manage'), 403);

        if ($request->has('topic_id')) {
            $validated = $request->validate([
                'topic_id' => ['required', 'integer', 'exists:chat_topics,id'],
            ]);

            $topic = ChatTopic::findOrFail($validated['topic_id']);

            try {
                $attach->execute($agent, $topic);
            } catch (InviteRejectedException $e) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return response()->json(['message' => 'Topic added successfully', 'topic' => $topic]);
        }

        $validated = $request->validate([
            'topic' => ['required', 'string', 'max:255'],
            'description' => 'required|string|max:500',
        ]);

        try {
            $topic = $createTopic->execute($agent, $request->user(), $validated['topic'], $validated['description']);
        } catch (InviteRejectedException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'Topic created successfully', 'topic' => $topic]);
    }

    public function update(
        Request $request,
        Agent $agent,
        ChatTopic $topic,
        UpdateChatTopicAction $updateTopic,
    ): JsonResponse {
        abort_if($request->user()->cannot('update', $agent), 404);
        abort_if(! has_permission_to_agent($agent, 'agent.topics.manage'), 403);

        abort_unless(
            $agent->topics()->where('chat_topics.id', $topic->id)->exists(),
            404,
            'Topic not found for this agent',
        );

        $validated = $request->validate([
            'topic' => ['required', 'string', 'max:255'],
            'description' => 'required|string|max:500',
        ]);

        try {
            $updateTopic->execute($topic, $request->user(), $validated['topic'], $validated['description']);
        } catch (InviteRejectedException $e) {
            $status = str_contains($e->getMessage(), 'System') ? 403 : 422;

            return response()->json(['message' => $e->getMessage()], $status);
        }

        return response()->json(['message' => 'Topic updated successfully', 'topic' => $topic]);
    }

    public function destroy(
        Request $request,
        Agent $agent,
        ChatTopic $topic,
        DetachTopicFromAgentAction $detach,
    ): JsonResponse {
        abort_if($request->user()->cannot('update', $agent), 404);
        abort_if(! has_permission_to_agent($agent, 'agent.topics.manage'), 403);

        abort_unless(
            $agent->topics()->where('chat_topics.id', $topic->id)->exists(),
            404,
            'Topic not found for this agent',
        );

        $detach->execute($agent, $topic);

        return response()->json(['message' => 'Topic removed successfully']);
    }
}
