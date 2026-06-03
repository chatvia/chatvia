<?php

namespace App\Http\Controllers\Agent\Handoff;

use Domain\Agent\Models\Agent;
use Domain\Conversation\Actions\Handoff\TakeoverConversationAction;
use Domain\Conversation\Data\Handoff\HandoffTakeoverResultData;
use Domain\Conversation\Exceptions\HandoffOperationException;
use Domain\Conversation\Models\Conversation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class TakeoverHandoffController
{
    public function __invoke(
        Request $request,
        Agent $agent,
        Conversation $conversation,
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

    private function guard(Agent $agent, Conversation $conversation): void
    {
        if (Gate::denies('access-agent', $agent) || $conversation->session->agent_id !== $agent->id) {
            throw HandoffOperationException::unauthorized();
        }
    }
}
