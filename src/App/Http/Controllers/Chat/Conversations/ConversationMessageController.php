<?php

namespace App\Http\Controllers\Chat\Conversations;

use Domain\Conversation\Data\Attachments\AttachmentData;
use Domain\Conversation\Models\Attachment;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Message;
use Domain\Conversation\Services\Memory\ChatMemory;
use Domain\Conversation\Services\Memory\TurnState;
use Illuminate\Http\Request;

class ConversationMessageController
{
    public function __invoke(Request $request, string $conversationId, ChatMemory $memory, TurnState $session): array
    {
        $agent = current_agent();

        $memory
            ->setAgent($agent)
            ->setExternalSessionId($session->firstOrCreateSession($agent))
            ->setConversationId($conversationId);

        abort_if(
            boolean: ! $memory->conversationExist(),
            code: 404
        );

        $messages = $memory->getMessagesForConversation();

        // Hydrate each message with its attachments so UserChatBubble can
        // render the file chips per-bubble. Looking up by chat_message_id
        // keyed by the external UUID of each message; assistant turns just
        // get empty arrays.
        $conversation = Conversation::query()
            ->where('external_id', $conversationId)
            ->first();

        if ($conversation) {
            $attachments = Attachment::query()
                ->where('chat_conversation_id', $conversation->id)
                ->get();

            // Resolve chat_message_id -> external_id with a single query so we
            // don't trigger lazy loading on each attachment's message relation.
            $externalIdByMessageId = Message::query()
                ->whereIn('id', $attachments->pluck('chat_message_id')->filter()->unique())
                ->pluck('external_id', 'id');

            // Orphan attachments — uploaded rows whose StoreMessageJob
            // hasn't run yet to claim chat_message_id. Cluster them onto the
            // latest user message so the chip shows up immediately after the
            // Home → Chat redirect, instead of only after a refresh once the
            // queue catches up.
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
        }

        return $messages;
    }
}
