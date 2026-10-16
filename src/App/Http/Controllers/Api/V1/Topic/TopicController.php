<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Topic;

use App\Http\Requests\Api\V1\Topic\ListApiTopicsRequest;
use App\Http\Requests\Api\V1\Topic\StoreApiTopicRequest;
use App\Http\Requests\Api\V1\Topic\UpdateApiTopicRequest;
use App\Http\Resources\Api\V1\Topic\TopicData;
use Dedoc\Scramble\Attributes\Group;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Topic\Actions\AttachExistingTopicToAgentAction;
use Domain\Conversation\Topic\Actions\CreateAgentTopicAction;
use Domain\Conversation\Topic\Actions\DetachTopicFromAgentAction;
use Domain\Conversation\Topic\Actions\UpdateTopicAction;
use Domain\Conversation\Topic\Models\Topic;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Support\Pagination\CursorPage;

/**
 * An agent's conversation topics over the public API — the same Actions as
 * the dashboard's Topics page, gated by `agent.topics.manage`. Topics belong
 * to the workspace (or are built-in system topics); the pivot decides which
 * ones the agent's conversation analysis tags with.
 */
#[Group(name: 'Topics', weight: 130)]
class TopicController
{
    /**
     * List topics
     *
     * The agent's topics, newest first — or, with `scope=available`, every
     * topic the workspace may attach (its own and the built-in system
     * topics). The list is paged with a cursor: to get the next page, send
     * the `next_cursor` of the response as `cursor`. On the last page
     * `next_cursor` is null.
     *
     * @return array{data: list<TopicData>, next_cursor: string|null}
     */
    public function index(ListApiTopicsRequest $request, Agent $agent): array
    {
        $query = $request->wantsAvailable()
            ? Topic::query()->forWorkspace($agent->workspace)
            : $agent->topics();

        $page = CursorPage::newestFirst($query, $request->perPage(), $request->cursor());

        return [
            'data' => array_map(TopicData::fromModel(...), $page->items()),
            'next_cursor' => $page->nextCursor()?->encode(),
        ];
    }

    /**
     * Add topic to agent
     *
     * Attaches an existing topic by `topic_id` (a system topic or one of the
     * workspace's own), or creates a new workspace topic from `topic` +
     * `description` and attaches it.
     */
    public function store(
        StoreApiTopicRequest $request,
        Agent $agent,
        AttachExistingTopicToAgentAction $attach,
        CreateAgentTopicAction $createTopic,
    ): JsonResponse {
        if ($request->isAttach()) {
            $topic = Topic::query()
                ->forWorkspace($agent->workspace)
                ->where('external_id', (string) $request->validated('topic_id'))
                ->firstOrFail();

            $attach->execute($agent, $topic);
        } else {
            $topic = $createTopic->execute(
                $agent,
                (string) $request->validated('topic'),
                (string) $request->validated('description'),
            );
        }

        return response()->json(['data' => TopicData::fromModel($topic)], 201);
    }

    /**
     * Update topic
     *
     * Renames / re-describes one of the workspace's own topics. System topics
     * are read-only and answer 403.
     *
     * @return array{data: TopicData}
     */
    public function update(UpdateApiTopicRequest $request, Agent $agent, Topic $topic, UpdateTopicAction $updateTopic): array
    {
        abort_unless($agent->topics()->where('topics.id', $topic->id)->exists(), 404);
        abort_if($topic->isSystem(), 403, __('System topics cannot be modified'));

        $updateTopic->execute(
            $agent,
            $topic,
            (string) $request->validated('topic'),
            (string) $request->validated('description'),
        );

        return ['data' => TopicData::fromModel($topic)];
    }

    /**
     * Remove topic from agent
     *
     * Detaches the topic from the agent. A workspace topic no agent uses any
     * more is deleted; system topics stay for other agents.
     */
    public function destroy(Agent $agent, Topic $topic, DetachTopicFromAgentAction $detach): Response
    {
        abort_unless($agent->topics()->where('topics.id', $topic->id)->exists(), 404);

        $detach->execute($agent, $topic);

        return response()->noContent();
    }
}
