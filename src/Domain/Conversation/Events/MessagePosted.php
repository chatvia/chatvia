<?php

declare(strict_types=1);

namespace Domain\Conversation\Events;

use Illuminate\Broadcasting\InteractsWithBroadcasting;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A message was posted into a handed-off conversation (by the visitor or the
 * operator). Broadcast at once to the conversation's presence channel as
 * `message`.
 */
class MessagePosted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithBroadcasting, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $conversationId,
        public string $messageId,
        public string $content,
        // A MessageRole value (`agent` is a human operator).
        public string $role,
        public ?string $senderName = null,
        public ?string $senderId = null,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PresenceChannel('chat.'.$this->conversationId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'message';
    }

    /** @return array{id: string, conversation_id: string, content: string, role: string, sender_name: string|null, sender_id: string|null, timestamp: string} */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->messageId,
            'conversation_id' => $this->conversationId,
            'content' => $this->content,
            'role' => $this->role,
            'sender_name' => $this->senderName,
            'sender_id' => $this->senderId,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
