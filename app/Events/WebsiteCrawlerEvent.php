<?php

namespace App\Events;

use App\Models\AgentWebsite;
use Illuminate\Broadcasting\InteractsWithBroadcasting;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WebsiteCrawlerEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithBroadcasting, InteractsWithSockets, SerializesModels;

    public function __construct(public AgentWebsite $website) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('agents.'.$this->website->agent_id.'.crawler'),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->website->id,
            'status' => $this->website->status,
        ];
    }
}
