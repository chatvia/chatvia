<?php

declare(strict_types=1);

namespace Domain\Conversation\Listeners;

use Domain\Conversation\Actions\Tools\RecordToolCallAuditAction;
use Domain\Conversation\Events\ToolCallCompleted;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Persists a {@see ToolCallCompleted} as an immutable governance audit row.
 * Queued onto the analytics queue so the write never blocks the chat reply
 * (same posture as the analytics recording listeners). The feature kill-switch
 * `governance.audit_enabled` short-circuits it before any DB work.
 */
class RecordToolCallAudit implements ShouldQueue
{
    /** Run only after the dispatching transaction has committed. */
    public bool $afterCommit = true;

    public function __construct(
        private readonly RecordToolCallAuditAction $action,
    ) {}

    // The queue name comes from config, which an attribute cannot read.
    public function viaQueue(): string
    {
        return (string) config('chatvia.analytics.record_queue', 'records');
    }

    public function handle(ToolCallCompleted $event): void
    {
        if (! config('chatvia.governance.audit_enabled', true)) {
            return;
        }

        $this->action->execute($event);
    }
}
