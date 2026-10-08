<?php

namespace Domain\Conversation\Events;

use Illuminate\Broadcasting\InteractsWithBroadcasting;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A conversation's handoff state changed (to a human, back to the AI, or
 * waiting). Broadcast at once to the conversation's presence channel so the
 * widget can switch between AI and human mode.
 */
class HandedOff implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithBroadcasting, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $conversationId,
        public string $status, // 'ai', 'pending', 'human', 'returned'
        public ?string $adminId = null,
        public ?string $adminName = null,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PresenceChannel('chat.'.$this->conversationId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'handoff';
    }

    public function broadcastWith(): array
    {
        return [
            'conversation_id' => $this->conversationId,
            'status' => $this->status,
            'admin_id' => $this->adminId,
            'admin_name' => $this->adminName,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
