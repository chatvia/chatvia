<?php

namespace App\Http\Controllers\Widget\Threads;

use Domain\Conversation\Data\Attachments\AttachmentData;
use Domain\Conversation\Models\Attachment;
use Domain\Conversation\Models\Message;
use Domain\Conversation\Services\Memory\ChatMemory;
use Domain\Widget\Services\WidgetVerification;
use Illuminate\Http\Request;

class ThreadMessageController
{
    public function __invoke(Request $request, ChatMemory $memory, WidgetVerification $verification)
    {
        $sessionId = $request->header('X-SESSION-ID');
        $threadId = $request->header('X-THREAD-ID');

        if (! $sessionId || ! $threadId) {
            return response()->json(['error' => 'Missing thread/session ID'], 400);
        }

        $agent = current_agent_from_widget();

        // Thread must belong to this agent — otherwise an attacker with a
        // thread id from agent A can pull its history while spoofing agent B.
        $conversation = $verification->verifyThreadId($threadId, $agent);

        abort_if(
            boolean: ! $conversation,
            code: 403,
            message: 'Invalid thread',
        );

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

        $messages = $chatMemory->getMessagesForConversation();

        // Hydrate per-message attachments so chips render under each user
        // bubble on history reload — mirrors ConversationMessageController
        // for the hosted chat side. Orphans (chat_message_id still NULL
        // because StoreMessageJob is queued) cluster onto the latest
        // user message so the chip doesn't disappear until the queue
        // catches up.
        $attachments = Attachment::query()
            ->where('chat_conversation_id', $conversation->id)
            ->get();

        $externalIdByMessageId = Message::query()
            ->whereIn('id', $attachments->pluck('chat_message_id')->filter()->unique())
            ->pluck('external_id', 'id');

        $lastUserExternalId = null;
        foreach ($messages as $msg) {
            if (($msg['role'] ?? null) === 'user' && isset($msg['id'])) {
                $lastUserExternalId = $msg['id'];
            }
        }

        $attachmentsByMessageId = $attachments->groupBy(
            fn (Attachment $a) => $a->chat_message_id !== null
                ? ($externalIdByMessageId[$a->chat_message_id] ?? null)
                : $lastUserExternalId
        );

        foreach ($messages as &$message) {
            $externalId = $message['id'] ?? null;
            $message['attachments'] = $externalId && isset($attachmentsByMessageId[$externalId])
                ? $attachmentsByMessageId[$externalId]->map(fn ($a): array => AttachmentData::fromModel($a)->toArray())->all()
                : [];
        }
        unset($message);

        return $messages;
    }
}
