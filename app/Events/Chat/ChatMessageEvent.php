<?php

namespace App\Events\Chat;

use Illuminate\Broadcasting\InteractsWithBroadcasting;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChatMessageEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithBroadcasting, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $conversationId,
        public string $messageId,
        public string $content,
        public string $role, // 'user', 'assistant', 'agent'
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
