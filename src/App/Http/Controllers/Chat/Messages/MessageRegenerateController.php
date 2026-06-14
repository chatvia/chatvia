<?php

namespace App\Http\Controllers\Chat\Messages;

use App\Http\Requests\Chat\RegenerateMessageRequest;
use Domain\Conversation\Actions\Messages\RegenerateMessageAction;
use Illuminate\Http\JsonResponse;

class MessageRegenerateController
{
    public function __invoke(
        RegenerateMessageRequest $request,
        RegenerateMessageAction $regenerate,
    ): JsonResponse {
        $result = $regenerate->execute(
            agent: current_agent(),
            conversationId: $request->string('conversation_id')->toString(),
            assistantMessageId: $request->string('assistant_message_id')->toString(),
            userMessageId: $request->string('user_message_id')->toString(),
        );

        if (! $result) {
            return response()->json(['error' => 'User message not found'], 404);
        }

        return response()->json([
            'success' => true,
            'user_message_content' => $result->userMessageContent,
            'created_at' => $result->createdAt,
        ]);
    }
}
