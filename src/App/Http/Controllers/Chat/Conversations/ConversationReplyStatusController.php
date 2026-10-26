<?php

declare(strict_types=1);

namespace App\Http\Controllers\Chat\Conversations;

use App\Http\Chat\HostedChatAgent;
use App\Http\Chat\HostedChatSession;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Services\Turn\RunningTurns;
use Illuminate\Http\JsonResponse;

/**
 * Whether a reply is still being written in one of the hosted-chat
 * visitor's conversations. A chat reloaded while its first reply streamed
 * asks this: it waits while the turn runs, and asks for the answer itself
 * only when none is coming. Another visitor's conversation is a 404.
 */
class ConversationReplyStatusController
{
    public function __invoke(
        string $conversationId,
        #[HostedChatAgent] Agent $agent,
        HostedChatSession $session,
        RunningTurns $runningTurns,
    ): JsonResponse {
        $sessionId = $session->find($agent);

        $owned = $sessionId !== null && Conversation::query()
            ->inSession($sessionId, $agent->id)
            ->where('external_id', $conversationId)
            ->exists();

        abort_unless($owned, 404);

        return response()->json(['running' => $runningTurns->isRunning($conversationId)]);
    }
}
