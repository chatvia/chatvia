<?php

namespace Domain\Knowledge\Source\Text\Events;

use Domain\Knowledge\Source\Text\Models\SourceText;
use Illuminate\Broadcasting\InteractsWithBroadcasting;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Pushes a text source's ingestion progress (0-100) to the agent's Text
 * sources page after each new chunk.
 */
class TextIngestionProgressed implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithBroadcasting, InteractsWithSockets, SerializesModels;

    public function __construct(
        public SourceText $sourceText,
        public string $agentExternalId,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('agents.'.$this->agentExternalId.'.texts.progress'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'TextIngestionProgressed';
    }

    public function broadcastWith(): array
    {
        return [
            'text_id' => $this->sourceText->id,
            'progress' => $this->sourceText->ingestionProgress(),
        ];
    }
}
