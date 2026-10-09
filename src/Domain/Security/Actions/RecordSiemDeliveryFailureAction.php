<?php

declare(strict_types=1);

namespace Domain\Security\Actions;

use Domain\Security\Data\SecurityActorData;
use Domain\Security\Enums\SecurityAuditAction;
use Domain\Security\Events\SecurityEventOccurred;
use Domain\Security\Listeners\SendSecurityEventToSiem;
use Domain\Security\Models\WorkspaceSecurityPolicy;

/**
 * Counts one failed SIEM webhook delivery (all retries used up) for a
 * workspace. After `chatvia.siem.max_failures` consecutive failures the
 * webhook is switched off, and that automated change is written to the
 * security audit log. A successful delivery resets the counter
 * ({@see DeliverSecurityWebhookAction}). Called from
 * {@see SendSecurityEventToSiem::failed()}.
 */
class RecordSiemDeliveryFailureAction
{
    public function execute(int $workspaceId): void
    {
        $policy = WorkspaceSecurityPolicy::query()
            ->where('workspace_id', $workspaceId)
            ->first();

        if ($policy === null) {
            return;
        }

        $wasEnabled = $policy->webhook_enabled;
        $failures = $policy->webhook_failures + 1;
        $stillEnabled = $wasEnabled && $failures < (int) config('chatvia.siem.max_failures', 15);

        $policy->forceFill([
            'webhook_failures' => $failures,
            'webhook_enabled' => $stillEnabled,
        ])->save();

        if ($wasEnabled && ! $stillEnabled) {
            event(SecurityEventOccurred::for(
                action: SecurityAuditAction::PolicyWebhookDisabled,
                workspaceId: $workspaceId,
                actor: SecurityActorData::system(),
                targetType: 'security_policy',
                targetId: $workspaceId,
                metadata: ['reason' => 'max_failures', 'failures' => $failures],
            ));
        }
    }
}
