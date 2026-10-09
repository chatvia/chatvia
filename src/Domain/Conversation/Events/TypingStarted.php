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
 * Someone in a handed-off conversation started or stopped typing. Broadcast
 * at once to the conversation's presence channel as `typing`.
 */
class TypingStarted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithBroadcasting, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $conversationId,
        public bool $isTyping,
        // `user` for the visitor, `agent` for the operator (MessageRole values).
        public string $typerType,
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

    /** @return array{conversation_id: string, is_typing: bool, typer_type: string, typer_name: string|null, typer_id: string|null, timestamp: string} */
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
