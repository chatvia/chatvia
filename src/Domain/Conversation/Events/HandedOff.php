<?php

namespace Domain\Conversation\Events;

use Illuminate\Broadcasting\InteractsWithBroadcasting;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class HandedOff implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithBroadcasting, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $conversationId,
        public string $status, // 'ai', 'pending', 'human', 'returned'
        public ?string $adminId = null,
        public ?string $adminName = null,
    ) {
        \Log::info('[HandedOff] Broadcasting handoff event', [
            'channel' => 'presence-chat.'.$this->conversationId,
            'status' => $this->status,
            'admin_id' => $this->adminId,
            'admin_name' => $this->adminName,
        ]);
    }

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
