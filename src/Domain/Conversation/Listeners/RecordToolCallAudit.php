<?php

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
    public function __construct(
        private readonly RecordToolCallAuditAction $action,
    ) {}

    public function viaQueue(): string
    {
        return (string) config('chatvia.analytics.queue', 'analytics');
    }

    public function handle(ToolCallCompleted $event): void
    {
        if (! config('chatvia.governance.audit_enabled', true)) {
            return;
        }

        $this->action->execute($event);
    }
}
