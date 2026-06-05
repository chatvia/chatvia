<?php

namespace App\Http\Controllers\Widget\Conversations;

use Domain\Conversation\Services\Memory\ChatMemory;
use Domain\Conversation\Services\Memory\MessageAttachmentHydrator;
use Domain\Widget\Services\WidgetVerification;
use Illuminate\Http\Request;

class ConversationMessageController
{
    public function __invoke(Request $request, ChatMemory $memory, WidgetVerification $verification, MessageAttachmentHydrator $hydrator)
    {
        $sessionId = $request->header('X-SESSION-ID');
        $conversationId = $request->header('X-CONVERSATION-ID');

        if (! $sessionId || ! $conversationId) {
            return response()->json(['error' => 'Missing thread/session ID'], 400);
        }

        $agent = current_agent_from_widget();

        // Conversation must belong to this agent — otherwise an attacker with a
        // conversation id from agent A can pull its history while spoofing agent B.
        $conversation = $verification->verifyConversationId($conversationId, $agent);

        abort_if(
            boolean: ! $conversation,
            code: 403,
            message: 'Invalid thread',
        );

        $chatMemory = $memory
            ->setAgent($agent)
            ->setExternalSessionId($sessionId)
            ->setConversationId($conversationId);

        // Check if conversation exists (handles tampered/deleted conversations)
        if (! $chatMemory->conversationExists()) {
            // Return empty messages array for non-existent conversations
            // The widget will handle this gracefully and can create a new conversation if needed
            return [];
        }

        $messages = $chatMemory->getMessagesForConversation();

        // Hydrate per-message attachments so chips render under each user
        // bubble on history reload.
        return $hydrator->hydrate($messages, $conversation);
    }
}
