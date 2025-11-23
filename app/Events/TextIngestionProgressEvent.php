<?php

namespace App\Events;

use App\Models\SourceText;
use Illuminate\Broadcasting\InteractsWithBroadcasting;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TextIngestionProgressEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithBroadcasting, InteractsWithSockets, SerializesModels;

    public function __construct(
        public SourceText $sourceText,
        public int $agentId,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('agents.'.$this->agentId.'.texts.progress'),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'text_id' => $this->sourceText->id,
            'progress' => $this->sourceText->ingestionProgress(),
        ];
    }
}
