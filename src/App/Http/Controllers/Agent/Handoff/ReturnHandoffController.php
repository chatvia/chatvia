<?php

namespace App\Http\Controllers\Agent\Handoff;

use Domain\Agent\Models\Agent;
use Domain\Conversation\Actions\Handoff\ReturnConversationToAiAction;
use Domain\Conversation\Data\Handoff\HandoffReturnResultData;
use Domain\Conversation\Exceptions\HandoffOperationException;
use Domain\Conversation\Models\Conversation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReturnHandoffController
{
    public function __invoke(
        Request $request,
        Agent $agent,
        Conversation $conversation,
        ReturnConversationToAiAction $returnToAi,
    ): JsonResponse|HandoffReturnResultData {
        try {
            if (! $conversation->isOperableBy($request->user(), $agent)) {
                throw HandoffOperationException::unauthorized();
            }

            if (! $returnToAi->execute($conversation, $request->user())) {
                throw HandoffOperationException::conflict('Unable to return this conversation to AI.');
            }

            return HandoffReturnResultData::ok();
        } catch (HandoffOperationException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], $e->status);
        }
    }
}
