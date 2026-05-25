<?php

namespace App\Http\Controllers\Widget;

use App\Widget\WidgetVerification;
use Domain\Chat\Services\Memory\ChatMemory;
use Illuminate\Http\Request;

class WidgetThreadMessagesController
{
    public function __invoke(Request $request, ChatMemory $memory, WidgetVerification $verification)
    {
        $sessionId = $request->header('X-SESSION-ID');
        $threadId = $request->header('X-THREAD-ID');

        if (! $sessionId || ! $threadId) {
            return response()->json(['error' => 'Missing thread/session ID'], 400);
        }

        $conversation = $verification->verifyThreadId($threadId);

        abort_if(
            boolean: ! $conversation,
            code: 403,
            message: 'Invalid thread',
        );

        $agent = current_agent_from_widget();

        $chatMemory = $memory
            ->setAgent($agent)
            ->setExternalSessionId($sessionId)
            ->setConversationId($threadId);

        // Check if conversation exists (handles tampered/deleted conversations)
        if (! $chatMemory->conversationExists()) {
            // Return empty messages array for non-existent conversations
            // The widget will handle this gracefully and can create a new conversation if needed
            return [];
        }

        return $chatMemory->getMessagesForConversation();
    }
}
