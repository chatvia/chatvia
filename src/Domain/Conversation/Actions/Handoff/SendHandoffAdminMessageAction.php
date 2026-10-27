<?php

namespace Domain\Conversation\Actions\Handoff;

use Domain\Conversation\Data\Handoff\SendHandoffAdminMessageData;
use Domain\Conversation\Data\Memory\ChatScopeData;
use Domain\Conversation\Data\Memory\MessageData;
use Domain\Conversation\Enums\MessageRole;
use Domain\Conversation\Events\MessagePosted;
use Domain\Conversation\Exceptions\ConversationNotHeldException;
use Domain\Conversation\Models\Message;
use Domain\Conversation\Services\Memory\ChatMemory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Posts a human operator's reply into a conversation they hold: writes it to
 * the live chat memory and the database under one external id, then
 * broadcasts it to the visitor. Returns null when the conversation's session
 * has no agent any more.
 */
class SendHandoffAdminMessageAction
{
    public function __construct(
        private readonly ChatMemory $memory,
    ) {}

    /**
     * @throws ConversationNotHeldException when the operator has not taken the conversation over
     */
    public function execute(SendHandoffAdminMessageData $data): ?Message
    {
        $conversation = $data->conversation;
        $admin = $data->admin;

        if (! $conversation->isHeldBy($admin)) {
            throw ConversationNotHeldException::forReplying();
        }

        $session = $conversation->session;
        $agent = $session?->agent;

        if (! $agent) {
            return null;
        }

        $messageId = Str::uuid7()->toString();

        $message = DB::transaction(function () use ($data, $conversation, $session, $agent, $messageId): Message {
            $this->memory
                ->withScope(new ChatScopeData($agent, $session->external_id, $conversation->external_id))
                ->appendMessage(new MessageData(
                    id: $messageId,
                    role: MessageRole::Operator,
                    content: $data->content,
                    type: MessageData::TYPE_TEXT,
                    timestamp: now()->timestamp,
                ));

            // Same id as the cached copy and the broadcast: read receipts look
            // the row up by this external_id, so a fresh id would never match.
            $message = Message::query()->create([
                'external_id' => $messageId,
                'workspace_id' => $conversation->workspace_id,
                'chat_conversation_id' => $conversation->id,
                'content' => $data->content,
                'role' => MessageRole::Operator,
                'metadata' => [
                    'admin_id' => $data->admin->id,
                    'admin_name' => $data->admin->name,
                ],
            ]);

            $conversation->update(['last_activity_at' => now()]);
            $session->update(['last_activity_at' => now()]);

            return $message;
        });

        MessagePosted::dispatch(
            $conversation->external_id,
            $messageId,
            $data->content,
            MessageRole::Operator->value,
            $admin->name,
            (string) $admin->id,
        );

        return $message;
    }
}
