<?php

namespace Domain\Security\Actions;

use App\Identity\Workspace\Models\User;
use Domain\Security\Events\SecurityEventOccurred;
use Domain\Security\Listeners\SendSecurityEventToSiem;
use Domain\Security\Models\WorkspaceSecurityPolicy;
use Illuminate\Support\Facades\Http;

/**
 * Delivers a {@see SecurityEventOccurred} to the workspace's configured SIEM
 * endpoint — an HMAC-SHA256-signed JSON POST. Requires the `siemWebhook`
 * capability (Ultra / Enterprise), re-checked here so a stale webhook left on a
 * since-downgraded workspace stops delivering even if `webhook_enabled` is set.
 *
 * On a non-2xx response or transport error it throws, so the queued
 * {@see SendSecurityEventToSiem} retries; that
 * listener's failed() handler tracks consecutive failures and auto-disables a
 * dead endpoint.
 */
class DeliverSecurityWebhookAction
{
    private const TIMEOUT_SECONDS = 10;

    public function execute(SecurityEventOccurred $event): void
    {
        $policy = WorkspaceSecurityPolicy::query()
            ->where('workspace_id', $event->workspaceId)
            ->first();

        if (! $this->shouldDeliver($policy, $event)) {
            return;
        }

        $body = (string) json_encode($this->payload($event));
        $signature = hash_hmac('sha256', $body, (string) $policy->webhook_secret);

        Http::timeout(self::TIMEOUT_SECONDS)
            ->withHeaders([
                'X-Chatvia-Event' => $event->action,
                'X-Chatvia-Signature' => 'sha256='.$signature,
            ])
            ->withBody($body, 'application/json')
            ->post($policy->webhook_url)
            ->throw();

        $policy->forceFill([
            'webhook_failures' => 0,
            'webhook_last_delivered_at' => now(),
        ])->save();
    }

    private function shouldDeliver(?WorkspaceSecurityPolicy $policy, SecurityEventOccurred $event): bool
    {
        if ($policy === null || ! $policy->webhook_enabled || blank($policy->webhook_url) || blank($policy->webhook_secret)) {
            return false;
        }

        // Capability re-checked at delivery, so a stale webhook on a since-
        // downgraded workspace stops delivering.
        $workspace = User::find($event->workspaceId);
        if ($workspace === null || ! $workspace->capabilities->siemWebhook) {
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
