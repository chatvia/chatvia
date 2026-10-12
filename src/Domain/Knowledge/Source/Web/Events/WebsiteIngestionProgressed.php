<?php

declare(strict_types=1);

namespace Domain\Knowledge\Source\Web\Events;

use Domain\Knowledge\Source\Enums\SourceStatus;
use Domain\Knowledge\Source\Web\Models\SourceWebsite;
use Illuminate\Broadcasting\InteractsWithBroadcasting;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Pushes a website's training progress (0-100) to the agent's Web sources page
 * while its URLs are scraped and chunked. The page jobs send the batch's
 * progress, capped at 99; 100 means the website is ingested.
 */
class WebsiteIngestionProgressed implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithBroadcasting, InteractsWithSockets, SerializesModels;

    public function __construct(
        public SourceWebsite $website,
        public string $agentExternalId,
        /** The job batch's progress; null for the final event, sent once the source is ingested. */
        public ?int $progress = null,
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

    /** @return array{website_id: int, progress: int} */
    public function broadcastWith(): array
    {
        return [
            'website_id' => $this->website->id,
            'progress' => $this->progress(),
        ];
    }

    private function progress(): int
    {
        if ($this->website->status === SourceStatus::Completed || $this->progress === null) {
            return $this->website->ingestionProgress();
        }

        return min($this->progress, 99);
    }
}
