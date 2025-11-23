<?php

namespace App\Events;

use App\Models\AgentWebsiteUrl;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class UrlIngestionEvent implements ShouldBroadcastNow
{
    use Dispatchable;

    public function __construct(
        public AgentWebsiteUrl $websiteUrl,
        public int $agentId
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('agents.'.$this->agentId.'.web.ingestion.url'),
        ];
    }

    public function broadcastWith()
    {
        return [
            'id' => $this->websiteUrl->id,
            'url' => $this->websiteUrl->url,
            'status' => $this->websiteUrl->status,
            'crawled_at' => $this->websiteUrl->crawled_at,
        ];
    }
}
