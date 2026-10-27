<?php

declare(strict_types=1);

namespace Domain\Evaluation\Events;

use Domain\Evaluation\Data\Resources\EvalRunResourceData;
use Domain\Evaluation\Models\EvalRun;
use Illuminate\Broadcasting\InteractsWithBroadcasting;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * An eval run's status/summary changed — it was just started (status: running)
 * or finalized (completed/failed). Carries the full run resource so the Evals
 * index can upsert the row and the run drill-down can refresh its header badge
 * and summary cards live.
 */
class EvalRunProgressed implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithBroadcasting, InteractsWithSockets, SerializesModels;

    public function __construct(
        public EvalRun $run,
        public string $agentExternalId,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("agents.{$this->agentExternalId}.evals"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'EvalRunProgressed';
    }

    /** @return array{run: array<string, mixed>} */
    public function broadcastWith(): array
    {
        return [
            'run' => EvalRunResourceData::fromModel($this->run)->toArray(),
        ];
    }
}
