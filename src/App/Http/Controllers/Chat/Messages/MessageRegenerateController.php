<?php

namespace App\Http\Controllers\Chat\Messages;

use App\Http\Chat\HostedChatAgent;
use App\Http\Chat\HostedChatSession;
use App\Http\Requests\Chat\RegenerateMessageRequest;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Actions\Messages\RegenerateMessageAction;
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

        if ($result === null) {
            return response()->json(['error' => __('User message not found')], 404);
        }

        return response()->json([
            'success' => true,
            'user_message_content' => $result->userMessageContent,
            'created_at' => $result->createdAt,
        ]);
    }
}
