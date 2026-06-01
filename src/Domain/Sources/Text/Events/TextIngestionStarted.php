<?php

namespace Domain\Sources\Text\Events;

use Domain\Agents\Models\Agent;
use Domain\Sources\Text\Models\SourceText;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class TextIngestionStarted implements ShouldBroadcastNow
{
    use Dispatchable;

    public function __construct(
        public SourceText $text,
        public Agent $agent
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('agents.'.$this->agent->external_id.'.ingestion.text'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'TextIngestionStarted';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->text->id,
            'status' => $this->text->status,
        ];
    }
}
