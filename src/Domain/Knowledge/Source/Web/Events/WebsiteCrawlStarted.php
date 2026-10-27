<?php

namespace Domain\Knowledge\Source\Web\Events;

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Source\Web\Models\SourceWebsite;
use Illuminate\Broadcasting\InteractsWithBroadcasting;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Tells the agent's Web sources page that a website's status changed:
 * discovery started or finished, training started or finished, or a step
 * failed. Despite the name it fires on every status change; the broadcast name
 * is part of the frontend contract and stays.
 */
class WebsiteCrawlStarted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithBroadcasting, InteractsWithSockets, SerializesModels;

    public string $agentExternalId;

    /**
     * @param  Agent|string  $agent  the agent's external id; batch callbacks
     *                               queued by an older release pass the model
     */
    public function __construct(
        public SourceWebsite $website,
        Agent|string $agent,
    ) {
        $this->agentExternalId = $agent instanceof Agent ? $agent->external_id : $agent;
    }

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("agents.{$this->agentExternalId}.crawler"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'WebsiteCrawlStarted';
    }

    /**
     * @return array{id: int, status: string|null}
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->website->id,
            'status' => $this->website->status?->value,
        ];
    }
}
