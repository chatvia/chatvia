<?php

namespace Domain\Knowledge\Source\Text\Events;

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Source\Text\Models\SourceText;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Tells the agent's Text sources page that a text's status changed: ingestion
 * started, finished or failed. Despite the name it fires on every status
 * change; the broadcast name is part of the frontend contract and stays.
 */
class TextIngestionStarted implements ShouldBroadcastNow
{
    use Dispatchable;

    public string $agentExternalId;

    /**
     * @param  Agent|string  $agent  the agent's external id; batch callbacks
     *                               queued by an older release pass the model
     */
    public function __construct(
        public SourceText $text,
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
            new PrivateChannel("agents.{$this->agentExternalId}.ingestion.text"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'TextIngestionStarted';
    }

    /**
     * @return array{id: int, status: string|null}
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->text->id,
            'status' => $this->text->status?->value,
        ];
    }
}
