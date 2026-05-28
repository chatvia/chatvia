<?php

namespace App\Http\Controllers\Api\V1\Chat;

use Dedoc\Scramble\Attributes\Group;
use Domain\Agents\Models\Agent;
use Domain\Chat\Models\ChatConversation;
use Domain\Chat\Models\ChatMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Read and delete chat conversations. Conversations are created implicitly
 * when end-users chat through the agent's URL.
 */
#[Group(name: 'Conversations', weight: 80)]
class ChatConversationController
{
    /**
     * List conversations
     *
     * Paginated by `?per_page=` (default 25, max 100) and `?page=`. Returns
     * conversations ordered by most recent activity.
     */
    public function index(Request $request, Agent $agent): JsonResponse
    {
        $perPage = min((int) $request->input('per_page', 25), 100);

        $conversations = ChatConversation::query()
            ->whereHas('session', fn ($q) => $q->where('agent_id', $agent->id))
            ->onlyVisible()
            ->orderByLatestActivity()
            ->paginate($perPage);

        return response()->json([
            'data' => $conversations->getCollection()->map(fn (ChatConversation $c): array => [
                'id' => $c->external_id,
                'title' => $c->title,
                'sentiment' => $c->sentiment,
                'tone' => $c->tone,
                'handoff_status' => $c->handoff_status,
                'last_activity_at' => $c->last_activity_at?->toIso8601String(),
                'created_at' => $c->created_at?->toIso8601String(),
            ])->all(),
            'meta' => [
                'current_page' => $conversations->currentPage(),
                'last_page' => $conversations->lastPage(),
                'per_page' => $conversations->perPage(),
                'total' => $conversations->total(),
            ],
        ]);
    }

    /**
     * Get conversation
     *
     * Returns the conversation with its message history.
     */
    public function show(Request $request, Agent $agent, ChatConversation $conversation): JsonResponse
    {
        abort_unless(
            $conversation->session->agent_id === $agent->id,
            404
        );

        $messages = $conversation->messages()->orderBy('id')->get();

        return response()->json([
            'data' => [
                'id' => $conversation->external_id,
                'title' => $conversation->title,
                'sentiment' => $conversation->sentiment,
                'tone' => $conversation->tone,
                'handoff_status' => $conversation->handoff_status,
                'created_at' => $conversation->created_at?->toIso8601String(),
                'messages' => $messages->map(fn (ChatMessage $m): array => [
                    'id' => $m->id,
                    'role' => $m->role ?? null,
                    'content' => $m->content ?? null,
                    'created_at' => $m->created_at?->toIso8601String(),
                ])->all(),
            ],
        ]);
    }

    /**
     * Delete conversation
     */
    public function destroy(Request $request, Agent $agent, ChatConversation $conversation): JsonResponse
    {
        abort_unless(
            $conversation->session->agent_id === $agent->id,
            404
        );

        $conversation->delete();

        return response()->json(null, 204);
    }
}
