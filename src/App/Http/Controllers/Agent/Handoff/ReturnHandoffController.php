<?php

namespace App\Http\Controllers\Agent\Handoff;

use Domain\Agent\Models\Agent;
use Domain\Conversation\Actions\Handoff\ReturnConversationToAiAction;
use Domain\Conversation\Data\Handoff\HandoffReturnResultData;
use Domain\Conversation\Exceptions\HandoffOperationException;
use Domain\Conversation\Models\Conversation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ReturnHandoffController
{
    public function __invoke(
        Request $request,
        Agent $agent,
        Conversation $conversation,
        ReturnConversationToAiAction $returnToAi,
    ): JsonResponse|HandoffReturnResultData {
        try {
            $this->guard($agent, $conversation);

            if (! $returnToAi->execute($conversation, $request->user())) {
                throw HandoffOperationException::conflict('Unable to return this conversation to AI.');
            }

            return HandoffReturnResultData::ok();
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
