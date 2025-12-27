<?php

namespace App\Http\Controllers\Chat;

use App\Http\Controllers\Controller;
use App\Services\Memory\ChatMemory;
use App\Services\Memory\ChatSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatMessageRegenerateController extends Controller
{
    public function __invoke(Request $request, ChatMemory $memory, ChatSession $session): JsonResponse
    {
        $request->validate([
            'conversation_id' => ['required', 'string'],
            'assistant_message_id' => ['required', 'string'],
            'user_message_id' => ['required', 'string'],
        ]);

        $conversationId = $request->input('conversation_id');
        $assistantMessageId = $request->input('assistant_message_id');
        $userMessageId = $request->input('user_message_id');

        $agent = current_agent();

        $memory
            ->setAgent($agent)
            ->setExternalSessionId($session->firstOrCreateChatSession($agent))
            ->setConversationId($conversationId);

        // Get the user message content and timestamps before deleting
        $userMessage = $memory->getMessageById($userMessageId);

        if (! $userMessage) {
            return response()->json(['error' => 'User message not found'], 404);
        }

        $userMessageContent = $userMessage->content ?? '';
        $userMessageCreatedAt = now()->toISOString();

        // Delete both messages
        $memory->deleteMessage($assistantMessageId);
        $memory->deleteMessage($userMessageId);

        return response()->json([
            'success' => true,
            'user_message_content' => $userMessageContent,
            'created_at' => $userMessageCreatedAt,
        ]);
    }
}
