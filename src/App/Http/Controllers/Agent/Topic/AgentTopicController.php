<?php

namespace App\Http\Controllers\Agent\Topic;

use App\Http\Requests\Agent\Topic\AttachOrCreateTopicRequest;
use App\Http\Requests\Agent\Topic\UpdateAgentTopicRequest;
use Domain\Agent\Data\Resources\Account\AgentResourceData;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Topic\Actions\AttachExistingTopicToAgentAction;
use Domain\Conversation\Topic\Actions\CreateAgentTopicAction;
use Domain\Conversation\Topic\Actions\DetachTopicFromAgentAction;
use Domain\Conversation\Topic\Actions\UpdateTopicAction;
use Domain\Conversation\Topic\Models\Topic;
use Support\Exceptions\DomainValidationException;
use Support\Exceptions\SystemTopicProtectedException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AgentTopicController
{
    public function index(Request $request, Agent $agent): Response|JsonResponse
    {
        abort_if($request->user()->cannot('view', $agent), 404);

        if ($request->wantsJson()) {
            $search = $request->input('search');

            if ($request->boolean('all')) {
                $query = Topic::query()
                    ->forUser($request->user())
                    ->orderBy('topic');

                if ($search) {
                    $query->where('topic', 'ilike', "%{$search}%");
                }

                return response()->json($query->get());
            }

            $query = $agent->topics()->orderBy('topics.topic');

            if ($search) {
                $query->where('topics.topic', 'ilike', "%{$search}%");
            }

            return response()->json($query->get(['topics.*']));
        }

        return Inertia::render('agents/topics/Manage', [
            'agent' => AgentResourceData::fromModel($agent),
        ]);
    }

    public function store(
        AttachOrCreateTopicRequest $request,
        Agent $agent,
        AttachExistingTopicToAgentAction $attach,
        CreateAgentTopicAction $createTopic,
    ): JsonResponse {
        if ($request->isAttach()) {
            // Scope through forUser (global or owned) so a body-supplied id
            // can't reach another account's private topic. Mirrors the same
            // scope the attachable-topics listing in index() uses.
            $topic = Topic::query()
                ->forUser($request->user())
                ->findOrFail((int) $request->validated('topic_id'));

            try {
                $attach->execute($agent, $topic);
            } catch (DomainValidationException $e) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return response()->json(['message' => 'Topic added successfully', 'topic' => $topic]);
        }

        try {
            $topic = $createTopic->execute(
                $agent,
                $request->user(),
                (string) $request->validated('topic'),
                (string) $request->validated('description'),
            );
        } catch (DomainValidationException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'Topic created successfully', 'topic' => $topic]);
    }

    public function update(
        UpdateAgentTopicRequest $request,
        Agent $agent,
        Topic $topic,
        UpdateTopicAction $updateTopic,
    ): JsonResponse {
        abort_unless(
            $agent->topics()->where('topics.id', $topic->id)->exists(),
            404,
            'Topic not found for this agent',
        );

        try {
            $updateTopic->execute(
                $topic,
                $request->user(),
                (string) $request->validated('topic'),
                (string) $request->validated('description'),
            );
        } catch (SystemTopicProtectedException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        } catch (DomainValidationException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'Topic updated successfully', 'topic' => $topic]);
    }

    public function destroy(
        Request $request,
        Agent $agent,
        Topic $topic,
        DetachTopicFromAgentAction $detach,
    ): JsonResponse {
        abort_if($request->user()->cannot('update', $agent), 404);

        abort_unless(
            $agent->topics()->where('topics.id', $topic->id)->exists(),
            404,
            'Topic not found for this agent',
        );

        $detach->execute($agent, $topic);

        return response()->json(['message' => 'Topic removed successfully']);
    }
}
