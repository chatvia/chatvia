<?php

namespace Domain\Security\Listeners;

use Domain\Security\Actions\DeliverSecurityWebhookAction;
use Domain\Security\Data\SecurityActorData;
use Domain\Security\Enums\SecurityAuditAction;
use Domain\Security\Events\SecurityEventOccurred;
use Domain\Security\Models\WorkspaceSecurityPolicy;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Support\Exceptions\UnsafeUrlException;
use Throwable;

/**
 * Fans a {@see SecurityEventOccurred} out to the workspace's SIEM webhook
 * (Enterprise / advanced compliance). Queued onto the analytics queue so
 * delivery never blocks the request that triggered it. The Action no-ops unless
 * the webhook is enabled, entitled, and the event passes the allowlist.
 *
 * Transient failures retry via the queue; once retries are exhausted, failed()
 * increments the consecutive-failure counter and auto-disables a dead endpoint
 * past the configured threshold (a success resets the counter in the Action).
 * A URL that fails the SSRF check is failed at once: retrying cannot fix it,
 * and it still counts towards the auto-disable threshold.
 */
class SendSecurityEventToSiem implements ShouldQueue
{
    use InteractsWithQueue;

    public int $tries = 3;

    public function __construct(
        private readonly DeliverSecurityWebhookAction $action,
    ) {}

    public function viaQueue(): string
    {
        return (string) config('chatvia.analytics.queue', 'analytics');
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [10, 60, 300];
    }

    public function handle(SecurityEventOccurred $event): void
    {
        try {
            $this->action->execute($event);
        } catch (UnsafeUrlException $e) {
            if ($this->job === null) {
                throw $e;
            }

            $this->fail($e);
        }
    }

    public function failed(SecurityEventOccurred $event, Throwable $exception): void
    {
        $policy = WorkspaceSecurityPolicy::query()
            ->where('workspace_id', $event->workspaceId)
            ->first();

        if ($policy === null) {
            return;
        }

        $wasEnabled = (bool) $policy->webhook_enabled;
        $failures = (int) $policy->webhook_failures + 1;
        $max = (int) config('chatvia.siem.max_failures', 15);
        $stillEnabled = $wasEnabled && $failures < $max;

        $policy->forceFill([
            'webhook_failures' => $failures,
            'webhook_enabled' => $stillEnabled,
        ])->save();

        // Record the automated disable so it surfaces in the audit log.
        if ($wasEnabled && ! $stillEnabled) {
            event(SecurityEventOccurred::for(
                action: SecurityAuditAction::PolicyWebhookDisabled,
                workspaceId: $event->workspaceId,
                actor: SecurityActorData::system(),
                targetType: 'security_policy',
                targetId: $event->workspaceId,
                metadata: ['reason' => 'max_failures', 'failures' => $failures],
            ));
        }
    }
}
