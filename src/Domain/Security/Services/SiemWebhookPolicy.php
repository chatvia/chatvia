<?php

declare(strict_types=1);

namespace Domain\Security\Services;

use Domain\Security\Events\SecurityEventOccurred;
use Domain\Security\Models\WorkspaceSecurityPolicy;
use Support\License\Entitlements;

/**
 * Finds the workspace policy whose SIEM webhook takes a security event. The
 * listener asks before queuing, so a workspace without SIEM never gets a job
 * (and the actor's details never sit in the queue); the delivery asks again
 * because the policy can change while the job waits. SIEM delivery is an
 * Enterprise feature: without an Enterprise key no policy takes an event.
 */
readonly class SiemWebhookPolicy
{
    public function __construct(
        private Entitlements $entitlements,
    ) {}

    public function accepts(SecurityEventOccurred $event): bool
    {
        return $this->for($event) instanceof WorkspaceSecurityPolicy;
    }

    public function for(SecurityEventOccurred $event): ?WorkspaceSecurityPolicy
    {
        if (! $this->entitlements->enterprise()) {
            return null;
        }

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
}
