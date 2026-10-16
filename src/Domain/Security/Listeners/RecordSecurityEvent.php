<?php

declare(strict_types=1);

namespace Domain\Security\Listeners;

use Domain\Security\Actions\RecordSecurityEventAction;
use Domain\Security\Events\SecurityEventOccurred;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Persists a {@see SecurityEventOccurred} as an immutable `security_audit_log`
 * row. Queued onto the analytics queue so the write never blocks the request
 * that triggered it (same posture as the tool-call / guardrail audit listeners).
 * The feature kill-switch `security_audit.enabled` short-circuits it before any
 * DB work.
 *
 * Request context (IP / User-Agent) is already denormalized onto the event by
 * the dispatcher, so running on a queue worker does not lose it.
 */
class RecordSecurityEvent implements ShouldQueue
{
    /** Run only after the dispatching transaction has committed. */
    public bool $afterCommit = true;

    public function __construct(
        private readonly RecordSecurityEventAction $action,
    ) {}

    // The queue name comes from config, which an attribute cannot read.
    public function viaQueue(): string
    {
        return (string) config('chatvia.analytics.queue', 'analytics');
    }

    public function handle(SecurityEventOccurred $event): void
    {
        if (! config('chatvia.security_audit.enabled', true)) {
            return;
        }

        $this->action->execute($event);
    }
}
