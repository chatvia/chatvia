<?php

namespace App\Http\Controllers\Chat\Messages;

use App\Http\Controllers\Controller;
use App\Http\Requests\Chat\RegenerateChatMessageRequest;
use Domain\Chat\Actions\RegenerateChatMessageAction;
use Illuminate\Http\JsonResponse;

class ChatMessageRegenerateController extends Controller
{
    public function __invoke(
        RegenerateChatMessageRequest $request,
        RegenerateChatMessageAction $regenerate,
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
