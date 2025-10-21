<?php

namespace App\Events;

use App\Models\AgentText;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class TextIngestionEvent implements ShouldBroadcastNow
{
    use Dispatchable;

    public function __construct(public AgentText $text) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('agents.'.$this->text->agent_id.'.ingestion.text'),
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
