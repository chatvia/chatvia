<?php

namespace App\Events;

use App\Models\AgentWebsiteUrl;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class UrlIngestionEvent implements ShouldBroadcastNow
{
    use Dispatchable;

    public function __construct(public AgentWebsiteUrl $websiteUrl) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('agents.'.$this->websiteUrl->agentWebsite->agent_id.'.web.ingestion.url'),
        ];
    }

    public function broadcastWith()
    {
        return [
            'id' => $this->websiteUrl->id,
            'url' => $this->websiteUrl->url,
            'status' => $this->websiteUrl->status,
        ];
    }
}
