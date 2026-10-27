<?php

declare(strict_types=1);

namespace Domain\Knowledge\Source\Web\Events;

use Domain\Knowledge\Source\Web\Models\AgentWebsiteUrl;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Support\Carbon;

/**
 * Tells the website's URL list that one page's status changed (training
 * started, finished or failed). Despite the name it fires on every status
 * change; the broadcast name is part of the frontend contract and stays.
 */
class UrlIngestionStarted implements ShouldBroadcastNow
{
    use Dispatchable;

    public function __construct(
        public AgentWebsiteUrl $websiteUrl,
        public string $agentExternalId,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("agents.{$this->agentExternalId}.web.ingestion.url"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'UrlIngestionStarted';
    }

    /** @return array{id: int, url: string, status: string|null, crawled_at: Carbon|null} */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->websiteUrl->id,
            'url' => $this->websiteUrl->url,
            'status' => $this->websiteUrl->status?->value,
            'crawled_at' => $this->websiteUrl->crawled_at,
        ];
    }
}
