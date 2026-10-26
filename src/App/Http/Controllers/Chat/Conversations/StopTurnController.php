<?php

declare(strict_types=1);

namespace App\Http\Controllers\Chat\Conversations;

use App\Http\Chat\HostedChatAgent;
use App\Http\Chat\HostedChatSession;
use App\Http\Requests\Chat\StopTurnRequest;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Services\Streaming\Requests\StreamRequestDataFactory;
use Domain\Conversation\Services\Turn\RunningTurns;
use Domain\Conversation\Services\Turn\TurnStops;
use Illuminate\Http\Response;
use Illuminate\Support\Sleep;

/**
 * The hosted chat's Stop button. A turn keeps running after its browser goes
 * away, so Stop asks the turn itself to end ({@see TurnStops}); it then keeps
 * its partial answer. The answer comes once the turn has ended (or after a
 * few seconds), so whatever the chat does next (send, edit, regenerate) runs
 * after the stopped reply is saved. Another visitor's conversation is a 404.
 */
class StopTurnController
{
    /** The longest the endpoint waits for the turn to end. */
    private const int WAIT_MS = 5000;

    private const int CHECK_EVERY_MS = 100;

    public function __invoke(
        StopTurnRequest $request,
        string $conversationId,
        #[HostedChatAgent] Agent $agent,
        HostedChatSession $session,
        StreamRequestDataFactory $requests,
        TurnStops $stops,
        RunningTurns $runningTurns,
    ): Response {
        $sessionId = $session->find($agent);

        $owned = $sessionId !== null && Conversation::query()
            ->inSession($sessionId, $agent->id)
            ->where('external_id', $conversationId)
            ->exists();

        abort_unless($owned, 404);

        $requestId = $requests->chatRequestId($agent, $sessionId, $conversationId, $request->requestId());

        if ($requestId !== null) {
            $stops->request($requestId);
        }

        for ($waited = 0; $waited < self::WAIT_MS && $runningTurns->isRunning($conversationId); $waited += self::CHECK_EVERY_MS) {
            Sleep::for(self::CHECK_EVERY_MS)->milliseconds();
        }

        return response()->noContent();
    }
}
