<?php

namespace App\Http\Controllers\Agents\Handoff;

use Domain\Agents\Models\Agent;
use Domain\Chat\Actions\Handoff\TakeoverConversationAction;
use Domain\Chat\Data\Handoff\HandoffTakeoverResultData;
use Domain\Chat\Exceptions\HandoffOperationException;
use Domain\Chat\Models\ChatConversation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class TakeoverHandoffController
{
    public function __invoke(
        Request $request,
        Agent $agent,
        ChatConversation $conversation,
        TakeoverConversationAction $takeover,
    ): JsonResponse|HandoffTakeoverResultData {
        try {
            $this->guard($agent, $conversation);

            if (! has_permission_to_agent($agent, 'agent.chat.takeover')) {
                throw HandoffOperationException::forbidden('You do not have permission to take over conversations.');
            }

            if (! $takeover->execute($conversation, $request->user())) {
                throw HandoffOperationException::conflict('Unable to take over this conversation. It may already be handled by another admin.');
            }

            return HandoffTakeoverResultData::ok($conversation);
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
