<?php

namespace App\Http\Controllers\Agent\Handoff;

use App\Http\Requests\Agent\Handoff\AgentHandoffTypingRequest;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Actions\Messages\BroadcastChatTypingAction;
use Domain\Conversation\Data\Handoff\HandoffTypingResultData;
use Domain\Conversation\Exceptions\HandoffOperationException;
use Domain\Conversation\Models\Conversation;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class HandoffTypingController
{
    public function __invoke(
        AgentHandoffTypingRequest $request,
        Agent $agent,
        Conversation $conversation,
        BroadcastChatTypingAction $broadcastTyping,
    ): JsonResponse|HandoffTypingResultData {
        try {
            $this->guard($agent, $conversation);

            $user = $request->user();

            if (! $conversation->isHandedOff() || $conversation->handoff_user_id !== $user->id) {
                throw HandoffOperationException::forbidden('You must take over this conversation before sending typing indicators.');
            }

            $broadcastTyping->execute($request->toData($conversation));

            return new HandoffTypingResultData;
        } catch (HandoffOperationException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], $e->status);
        }
    }

    private function guard(Agent $agent, Conversation $conversation): void
    {
        if (Gate::denies('access-agent', $agent) || $conversation->session->agent_id !== $agent->id) {
            throw HandoffOperationException::unauthorized();
        }
    }
}
