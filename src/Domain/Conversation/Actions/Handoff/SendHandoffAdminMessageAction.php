<?php

namespace Domain\Conversation\Actions\Handoff;

use Domain\Conversation\Data\Handoff\SendHandoffAdminMessageData;
use Domain\Conversation\Events\MessagePosted;
use Domain\Conversation\Models\Message;
use Domain\Conversation\Services\Memory\ChatMemory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Posts a human operator's reply into a conversation they have taken over:
 * writes it to the live chat cache and the database under one external id,
 * then broadcasts it to the visitor.
 */
class SendHandoffAdminMessageAction
{
    public function __construct(
        private readonly ChatMemory $chatMemory,
    ) {}

    public function execute(SendHandoffAdminMessageData $data): ?Message
    {
        $conversation = $data->conversation;
        $admin = $data->admin;

        if (! $conversation->isHandedOff() || $conversation->handoff_user_id !== $admin->id) {
            return null;
        }

        $session = $conversation->session;
        $agent = $session?->agent;

        if (! $agent) {
            return null;
        }

        $messageId = Str::uuid()->toString();

        $message = DB::transaction(function () use ($conversation, $admin, $session, $agent, $messageId, $data) {
            $this->chatMemory
                ->withContext($agent, $session->external_id, $conversation->external_id)
                ->addMessage(
                    role: 'agent',
                    message: $data->content,
                    messageId: $messageId,
                    append: [
                        'admin_id' => $admin->id,
                        'admin_name' => $admin->name,
                    ],
                );

            // Same id as the cached copy and the broadcast: read receipts look
            // the row up by this external_id, so a fresh id would never match.
            $message = Message::create([
                'external_id' => $messageId,
                'chat_conversation_id' => $conversation->id,
                'content' => $data->content,
                'role' => 'agent',
                'metadata' => [
                    'admin_id' => $admin->id,
                    'admin_name' => $admin->name,
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
            'agent',
            $admin->name,
            (string) $admin->id,
        );

        return $message;
    }
}
