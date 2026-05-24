<?php

namespace Domain\Sources\Web\Events;

use Domain\Agents\Models\Agent;
use Domain\Sources\Web\Models\SourceWebsite;
use Illuminate\Broadcasting\InteractsWithBroadcasting;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WebsiteCrawlerEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithBroadcasting, InteractsWithSockets, SerializesModels;

    public function __construct(
        public SourceWebsite $website,
        public Agent $agent
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('agents.'.$this->agent->external_id.'.crawler'),
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
