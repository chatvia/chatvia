<?php

namespace App\Http\Controllers\Agents\Handoff;

use App\Http\Requests\Agents\Handoff\AgentHandoffTypingRequest;
use Domain\Agents\Models\Agent;
use Domain\Chat\Actions\Messages\BroadcastChatTypingAction;
use Domain\Chat\Data\Handoff\HandoffTypingResultData;
use Domain\Chat\Exceptions\HandoffOperationException;
use Domain\Chat\Models\ChatConversation;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class HandoffTypingController
{
    public function __invoke(
        AgentHandoffTypingRequest $request,
        Agent $agent,
        ChatConversation $conversation,
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

    private function guard(Agent $agent, ChatConversation $conversation): void
    {
        if (Gate::denies('access-agent', $agent) || $conversation->session->agent_id !== $agent->id) {
            throw HandoffOperationException::unauthorized();
        }
    }
}
