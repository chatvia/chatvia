<?php

namespace App\Http\Controllers\Agent\Handoff;

use App\Http\Requests\Agent\Handoff\AgentHandoffMessageRequest;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Actions\Handoff\SendHandoffAdminMessageAction;
use Domain\Conversation\Data\Handoff\HandoffMessageResultData;
use Domain\Conversation\Data\Handoff\HandoffMessageResultPayloadData;
use Domain\Conversation\Exceptions\HandoffOperationException;
use Domain\Conversation\Models\Conversation;
use Illuminate\Http\JsonResponse;

class SendHandoffMessageController
{
    public function __invoke(
        AgentHandoffMessageRequest $request,
        Agent $agent,
        Conversation $conversation,
        SendHandoffAdminMessageAction $sendMessage,
    ): JsonResponse|HandoffMessageResultData {
        try {
            $user = $request->user();

            if (! $conversation->isOperableBy($user, $agent)) {
                throw HandoffOperationException::unauthorized();
            }

            if (! $conversation->isHandedOff() || $conversation->handoff_user_id !== $user->id) {
                throw HandoffOperationException::forbidden('You must take over this conversation before sending messages.');
            }

            $message = $sendMessage->execute($request->toData($conversation));

            if (! $message) {
                throw HandoffOperationException::failed('Failed to send message.');
            }

            return new HandoffMessageResultData(
                success: true,
                message: HandoffMessageResultPayloadData::fromMessage($message),
            );
        } catch (HandoffOperationException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], $e->status);
        }
    }
}
