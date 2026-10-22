<?php

namespace Domain\Security\Actions;

use Domain\Security\Events\SecurityEventOccurred;
use Domain\Security\Listeners\SendSecurityEventToSiem;
use Domain\Security\Models\WorkspaceSecurityPolicy;
use Support\Exceptions\UnsafeUrlException;
use Support\Http\SignedWebhookSender;

/**
 * Delivers a {@see SecurityEventOccurred} to the workspace's configured SIEM
 * endpoint — an HMAC-SHA256-signed JSON POST sent through
 * {@see SignedWebhookSender}, which re-checks the URL against SSRF right before
 * sending and never follows redirects.
 *
 * On a non-2xx response or transport error it throws, so the queued
 * {@see SendSecurityEventToSiem} retries; that listener's failed() handler
 * tracks consecutive failures and auto-disables a dead endpoint. An unsafe URL
 * throws {@see UnsafeUrlException}, which the listener fails without retrying.
 *
 * Each step reads the policy once: {@see wants()} when the event is queued,
 * and {@see execute()} when the job sends it.
 */
class DeliverSecurityWebhookAction
{
    /** How fresh a stored delivery time may be before a new one is written. */
    private const int DELIVERED_AT_WRITE_SECONDS = 60;

    public function __construct(
        private readonly SignedWebhookSender $sender,
    ) {}

    public function execute(SecurityEventOccurred $event): void
    {
        $policy = $this->deliverablePolicy($event);

        if (! $policy instanceof WorkspaceSecurityPolicy) {
            return;
        }

        $this->sender->send(
            url: (string) $policy->webhook_url,
            event: $event->action,
            payload: $this->payload($event),
            secret: (string) $policy->webhook_secret,
        );

        $this->recordDelivery($policy);
    }

    /**
     * Resets the failure counter and stamps the delivery time. A healthy
     * endpoint gets many events, so the row is written only when there is a
     * count to reset or the stored time is over a minute old.
     */
    private function recordDelivery(WorkspaceSecurityPolicy $policy): void
    {
        $lastDelivered = $policy->webhook_last_delivered_at;

        if ($policy->webhook_failures === 0 && $lastDelivered?->greaterThan(now()->subSeconds(self::DELIVERED_AT_WRITE_SECONDS))) {
            return;
        }

        $policy->forceFill([
            'webhook_failures' => 0,
            'webhook_last_delivered_at' => now(),
        ])->save();
    }

    /**
     * Whether the workspace's SIEM webhook takes this event. The listener asks
     * before queuing, so a workspace without SIEM never gets a job (and the
     * actor's details never sit in the queue); execute() asks again because
     * the policy can change while the job waits.
     */
    public function wants(SecurityEventOccurred $event): bool
    {
        return $this->deliverablePolicy($event) instanceof WorkspaceSecurityPolicy;
    }

    private function deliverablePolicy(SecurityEventOccurred $event): ?WorkspaceSecurityPolicy
    {
        $policy = WorkspaceSecurityPolicy::query()
            ->where('workspace_id', $event->workspaceId)
            ->first();

        if (! $policy instanceof WorkspaceSecurityPolicy || ! $policy->is_webhook_enabled || blank($policy->webhook_url) || blank($policy->webhook_secret)) {
            return null;
        }

        // Per-action allowlist; null = deliver every event.
        $allowlist = $policy->webhook_events;

        return ! is_array($allowlist) || in_array($event->action, $allowlist, true) ? $policy : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(SecurityEventOccurred $event): array
    {
        return [
            'event' => $event->action,
            'outcome' => $event->outcome,
            'workspace_id' => $event->workspaceId,
            'timestamp' => now()->toIso8601String(),
            'actor' => [
                'id' => $event->userId,
                'name' => $event->userName,
                'email' => $event->userEmail,
                'ip' => $event->ip,
            ],
            'target' => [
                'type' => $event->targetType,
                'id' => $event->targetId,
                'label' => $event->targetLabel,
            ],
            'metadata' => json_decode($event->metadata, true),
        ];
    }
}
