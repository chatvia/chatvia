<?php

namespace Domain\Chat\Actions\Handoff;

use Domain\Chat\Data\Handoff\SendHandoffAdminMessageData;
use Domain\Chat\Events\ChatMessagePosted;
use Domain\Chat\Models\ChatMessage;
use Domain\Chat\Services\Memory\ChatMemory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SendHandoffAdminMessageAction
{
    public function __construct(
        private readonly ChatMemory $chatMemory,
    ) {}

    public function execute(SendHandoffAdminMessageData $data): ?ChatMessage
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
                ->setAgent($agent)
                ->setExternalSessionId($session->external_id)
                ->setConversationId($conversation->external_id)
                ->addMessage(
                    role: 'agent',
                    message: $data->content,
                    messageId: $messageId,
                    append: [
                        'admin_id' => $admin->id,
                        'admin_name' => $admin->name,
                    ],
                );

            $message = ChatMessage::create([
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

        ChatMessagePosted::dispatch(
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
