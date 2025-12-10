<?php

namespace App\Events;

use App\Models\SourceText;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class TextIngestionEvent implements ShouldBroadcastNow
{
    use Dispatchable;

    public function __construct(
        public SourceText $text,
        public string $agentExternalId
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('agents.'.$this->agentExternalId.'.ingestion.text'),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->text->id,
            'status' => $this->text->status,
        ];
    }
}
