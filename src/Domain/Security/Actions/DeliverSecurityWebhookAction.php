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
 */
class DeliverSecurityWebhookAction
{
    public function __construct(
        private readonly SignedWebhookSender $sender,
    ) {}

    public function execute(SecurityEventOccurred $event): void
    {
        $policy = WorkspaceSecurityPolicy::query()
            ->where('workspace_id', $event->workspaceId)
            ->first();

        if (! $this->shouldDeliver($policy, $event)) {
            return;
        }

        $this->sender->send(
            url: (string) $policy->webhook_url,
            event: $event->action,
            payload: $this->payload($event),
            secret: (string) $policy->webhook_secret,
        );

        $policy->forceFill([
            'webhook_failures' => 0,
            'webhook_last_delivered_at' => now(),
        ])->save();
    }

    private function shouldDeliver(?WorkspaceSecurityPolicy $policy, SecurityEventOccurred $event): bool
    {
        if (! $policy instanceof WorkspaceSecurityPolicy || ! $policy->is_webhook_enabled || blank($policy->webhook_url) || blank($policy->webhook_secret)) {
            return false;
        }

        // Per-action allowlist; null = deliver every event.
        $allowlist = $policy->webhook_events;

        return ! is_array($allowlist) || in_array($event->action, $allowlist, true);
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
