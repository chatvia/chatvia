<?php

namespace App\Http\Controllers\Agents\Handoff;

use App\Http\Requests\Agents\Handoff\AgentHandoffMessageRequest;
use Domain\Agents\Models\Agent;
use Domain\Chat\Actions\Handoff\SendHandoffAdminMessageAction;
use Domain\Chat\Data\Handoff\HandoffMessageResultData;
use Domain\Chat\Data\Handoff\HandoffMessageResultPayloadData;
use Domain\Chat\Exceptions\HandoffOperationException;
use Domain\Chat\Models\ChatConversation;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class SendHandoffMessageController
{
    public function __invoke(
        AgentHandoffMessageRequest $request,
        Agent $agent,
        ChatConversation $conversation,
        SendHandoffAdminMessageAction $sendMessage,
    ): JsonResponse|HandoffMessageResultData {
        try {
            $this->guard($agent, $conversation);

            $user = $request->user();

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

    private function guard(Agent $agent, ChatConversation $conversation): void
    {
        if (Gate::denies('access-agent', $agent) || $conversation->session->agent_id !== $agent->id) {
            throw HandoffOperationException::unauthorized();
        }
    }
}
