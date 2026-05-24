<?php

namespace Domain\Chat\Events;

use Illuminate\Broadcasting\InteractsWithBroadcasting;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChatTypingEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithBroadcasting, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $conversationId,
        public bool $isTyping,
        public string $typerType, // 'user', 'agent'
        public ?string $typerName = null,
        public ?string $typerId = null,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PresenceChannel('chat.'.$this->conversationId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'typing';
    }

    public function broadcastWith(): array
    {
        return [
            'conversation_id' => $this->conversationId,
            'is_typing' => $this->isTyping,
            'typer_type' => $this->typerType,
            'typer_name' => $this->typerName,
            'typer_id' => $this->typerId,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
