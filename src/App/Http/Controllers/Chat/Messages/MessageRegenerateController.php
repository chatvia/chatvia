<?php

namespace App\Http\Controllers\Chat\Messages;

use App\Http\Chat\HostedChatAgent;
use App\Http\Chat\HostedChatSession;
use App\Http\Requests\Chat\RegenerateMessageRequest;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Actions\Messages\RegenerateMessageAction;
use Domain\Conversation\Data\Messages\RegeneratedTurnData;
use Illuminate\Http\JsonResponse;

/**
 * Removes the last exchange from the hosted-chat history so the client can
 * send the visitor's message again for a new answer.
 */
class MessageRegenerateController
{
    public function __invoke(
        RegenerateMessageRequest $request,
        #[HostedChatAgent] Agent $agent,
        RegenerateMessageAction $regenerate,
        HostedChatSession $session,
    ): JsonResponse {
        $result = $regenerate->execute(
            $session->scope($agent, $request->conversationId()),
            $request->assistantMessageId(),
            $request->userMessageId(),
        );

        if (! $result instanceof RegeneratedTurnData) {
            return response()->json(['error' => __('User message not found')], 404);
        }

        // An explicit 200: the POST removed a pair, it created nothing.
        return response()->json($result);
    }
}
