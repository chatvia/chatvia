<?php

namespace App\Http\Controllers\Agents\Handoff;

use App\Models\Agent;
use App\Models\ChatConversation;
use App\Services\Memory\ChatMemory;
use Illuminate\Http\JsonResponse;

class HandoffMarkReadController
{
    public function __construct(
        private readonly ChatMemory $chatMemory,
    ) {}

    public function __invoke(Agent $agent, ChatConversation $conversation): JsonResponse
    {
        $session = $conversation->session;

        if (! $session || $session->agent_id !== $agent->id) {
            return response()->json(['error' => 'Conversation not found'], 404);
        }

        $markedCount = $this->chatMemory
            ->setAgent($agent)
            ->setExternalSessionId($session->external_id)
            ->setConversationId($conversation->external_id)
            ->markMessagesAsRead('admin'); // Admin is reading user messages

        return response()->json([
            'marked_count' => $markedCount,
        ]);
    }
}
