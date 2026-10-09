<?php

declare(strict_types=1);

namespace Domain\Knowledge\Source\Web\Events;

use Domain\Knowledge\Source\Web\Models\SourceWebsite;
use Illuminate\Broadcasting\InteractsWithBroadcasting;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Pushes a website's training progress (0-100) to the agent's Web sources page
 * while its URLs are scraped and chunked.
 */
class WebsiteIngestionProgressed implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithBroadcasting, InteractsWithSockets, SerializesModels;

    public function __construct(
        public SourceWebsite $website,
        public string $agentExternalId
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('agents.'.$this->agentExternalId.'.crawler.progress'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'WebsiteIngestionProgressed';
    }

    public function broadcastWith(): array
    {
        return [
            'website_id' => $this->website->id,
            'progress' => $this->website->ingestionProgress(),
        ];
    }
}
