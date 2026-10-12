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
use Domain\Conversation\Topic\Exceptions\SystemTopicProtectedException;
use Domain\Conversation\Topic\Models\Topic;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The agent's topics page: lists the agent's topics (or every topic the
 * workspace may attach), attaches or creates one, renames one, and removes
 * one from the agent. Access to the agent and the `agent.topics.manage`
 * permission are checked by route middleware.
 */
class AgentTopicController
{
    public function index(Request $request, Agent $agent): Response|JsonResponse
    {
        if ($request->wantsJson()) {
            $search = $request->input('search');

            if ($request->boolean('all')) {
                // Topics belong to the workspace owner, so a teammate sees the
                // same list as the owner.
                $query = Topic::query()
                    ->forWorkspace($agent->workspace)
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
            // Scope through forUser (global or owned by this workspace) so a
            // body-supplied id can't reach another account's private topic.
            // Mirrors the same scope the attachable-topics listing in index() uses.
            $topic = Topic::query()
                ->forWorkspace($agent->workspace)
                ->findOrFail((int) $request->validated('topic_id'));

            $attach->execute($agent, $topic);

            return response()->json(['message' => __('Topic added successfully'), 'topic' => $topic]);
        }

        $topic = $createTopic->execute(
            $agent,
            (string) $request->validated('topic'),
            (string) $request->validated('description'),
        );

        return response()->json(['message' => __('Topic created successfully'), 'topic' => $topic]);
    }

    public function update(
        UpdateAgentTopicRequest $request,
        Agent $agent,
        Topic $topic,
        UpdateTopicAction $updateTopic,
    ): JsonResponse {
        abort_unless($agent->topics()->where('topics.id', $topic->id)->exists(), 404, __('Topic not found for this agent'));

        try {
            $updateTopic->execute(
                $agent,
                $topic,
                (string) $request->validated('topic'),
                (string) $request->validated('description'),
            );
        } catch (SystemTopicProtectedException $e) {
            // Caught here because the global DomainValidationException mapping
            // would turn this subclass into a 422.
            return response()->json(['message' => $e->getMessage()], 403);
        }

        return response()->json(['message' => __('Topic updated successfully'), 'topic' => $topic]);
    }

    public function destroy(
        Agent $agent,
        Topic $topic,
        DetachTopicFromAgentAction $detach,
    ): JsonResponse {
        abort_unless($agent->topics()->where('topics.id', $topic->id)->exists(), 404, __('Topic not found for this agent'));

        $detach->execute($agent, $topic);

        return response()->json(['message' => __('Topic removed successfully')]);
    }
}
