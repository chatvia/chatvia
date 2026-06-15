<?php

namespace Domain\Evaluation\Events;

use Domain\Evaluation\Actions\FinalizeEvalRunAction;
use Domain\Evaluation\Data\Resources\EvalResultResourceData;
use Domain\Evaluation\Models\EvalResult;
use Illuminate\Broadcasting\InteractsWithBroadcasting;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * One eval case finished scoring. Carries the fresh per-case result plus the
 * run's running pass/fail tally so the Evals index and the run drill-down update
 * live without polling. The run's own counts aren't persisted until
 * {@see FinalizeEvalRunAction}, so the tally is
 * derived from the immutable results written so far.
 */
class EvalCaseScored implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithBroadcasting, InteractsWithSockets, SerializesModels;

    public function __construct(
        public EvalResult $result,
        public string $runExternalId,
        public string $agentExternalId,
        public int $passedCount,
        public int $failedCount,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('agents.'.$this->agentExternalId.'.evals'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'EvalCaseScored';
    }

    public function broadcastWith(): array
    {
        return [
            'run_external_id' => $this->runExternalId,
            'result' => EvalResultResourceData::fromModel($this->result)->toArray(),
            'passed_count' => $this->passedCount,
            'failed_count' => $this->failedCount,
        ];
    }
}
