<?php

declare(strict_types=1);

namespace Domain\Security\Listeners;

use Domain\Security\Actions\DeliverSecurityWebhookAction;
use Domain\Security\Actions\RecordSiemDeliveryFailureAction;
use Domain\Security\Events\SecurityEventOccurred;
use Domain\Security\Services\SiemWebhookPolicy;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Queue\InteractsWithQueue;
use Support\Exceptions\UnsafeUrlException;
use Throwable;

/**
 * Fans a {@see SecurityEventOccurred} out to the workspace's SIEM webhook
 * (Enterprise / advanced compliance). Queued onto the analytics queue so
 * delivery never blocks the request that triggered it. Nothing is queued unless
 * the webhook is enabled and the event passes the allowlist; the Action checks
 * again when the job runs.
 *
 * Transient failures retry via the queue; once retries are exhausted, failed()
 * hands over to {@see RecordSiemDeliveryFailureAction}, which counts the
 * failure and auto-disables a dead endpoint past the configured threshold (a
 * success resets the counter in the delivery Action).
 * A URL that fails the SSRF check is failed at once: retrying cannot fix it,
 * and it still counts towards the auto-disable threshold.
 */
// A SIEM endpoint that fails once often answers on the next try.
#[Tries(3)]
// Longer and longer waits, so a restarting endpoint has time to come back.
#[Backoff(10, 60, 300)]
class SendSecurityEventToSiem implements ShouldQueue
{
    use InteractsWithQueue;

    /** Run only after the dispatching transaction has committed. */
    public bool $afterCommit = true;

    public function __construct(
        private readonly DeliverSecurityWebhookAction $action,
        private readonly RecordSiemDeliveryFailureAction $recordFailure,
        private readonly SiemWebhookPolicy $policies,
    ) {}

    // The queue name comes from config, which an attribute cannot read.
    public function viaQueue(): string
    {
        return (string) config('chatvia.analytics.queue', 'analytics');
    }

    public function shouldQueue(SecurityEventOccurred $event): bool
    {
        return $this->policies->accepts($event);
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
        $this->recordFailure->execute($event->workspaceId);
    }
}
