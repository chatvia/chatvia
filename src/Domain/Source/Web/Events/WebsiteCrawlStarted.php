<?php

namespace Domain\Source\Web\Events;

use Domain\Agent\Models\Agent;
use Domain\Source\Web\Models\SourceWebsite;
use Illuminate\Broadcasting\InteractsWithBroadcasting;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WebsiteCrawlStarted implements ShouldBroadcastNow
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

    public function broadcastAs(): string
    {
        return 'WebsiteCrawlStarted';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->website->id,
            'status' => $this->website->status,
        ];
    }
}
