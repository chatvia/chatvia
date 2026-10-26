<?php

declare(strict_types=1);

namespace Domain\Guardrail\Listeners;

use Domain\Guardrail\Actions\RecordGuardrailEventAction;
use Domain\Guardrail\Events\GuardrailTriggered;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Persists a {@see GuardrailTriggered} as an immutable `guardrail_events` row.
 * Queued onto the analytics queue so the write never blocks the chat reply
 * (same posture as the governance audit listener). The `guardrails.enabled`
 * kill-switch short-circuits it before any DB work.
 */
class RecordGuardrailEvent implements ShouldQueue
{
    /** Run only after the dispatching transaction has committed. */
    public bool $afterCommit = true;

    public function __construct(
        private readonly RecordGuardrailEventAction $action,
    ) {}

    // The queue name comes from config, which an attribute cannot read.
    public function viaQueue(): string
    {
        return (string) config('chatvia.analytics.record_queue', 'records');
    }

    public function handle(GuardrailTriggered $event): void
    {
        if (! config('chatvia.guardrails.enabled', true)) {
            return;
        }

        $this->action->execute($event);
    }
}
