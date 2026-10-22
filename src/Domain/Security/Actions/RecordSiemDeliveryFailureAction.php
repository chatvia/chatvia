<?php

declare(strict_types=1);

namespace Domain\Security\Actions;

use Domain\Security\Data\SecurityActorData;
use Domain\Security\Enums\SecurityAuditAction;
use Domain\Security\Events\SecurityEventOccurred;
use Domain\Security\Listeners\SendSecurityEventToSiem;
use Illuminate\Support\Facades\DB;

/**
 * Counts one failed SIEM webhook delivery (all retries used up) for a
 * workspace. After `chatvia.siem.max_failures` consecutive failures the
 * webhook is switched off, and that automated change is written to the
 * security audit log. A successful delivery resets the counter
 * ({@see DeliverSecurityWebhookAction}). Called from
 * {@see SendSecurityEventToSiem::failed()}.
 *
 * One atomic UPDATE: two failures landing together both count, and only the
 * call that turns the webhook off (OLD on, NEW off, from PG 18's RETURNING)
 * writes the audit event, so it is written exactly once.
 */
class RecordSiemDeliveryFailureAction
{
    public function execute(int $workspaceId): void
    {
        $row = DB::selectOne(<<<'SQL'
            UPDATE workspace_security_policies SET
                webhook_failures = webhook_failures + 1,
                is_webhook_enabled = is_webhook_enabled AND webhook_failures + 1 < ?,
                updated_at = ?
            WHERE workspace_id = ?
            RETURNING NEW.webhook_failures AS failures,
                OLD.is_webhook_enabled AS was_enabled,
                NEW.is_webhook_enabled AS is_enabled
        SQL, [(int) config('chatvia.siem.max_failures', 15), now(), $workspaceId]);

        // No policy row: nothing to count.
        if ($row === null || ! $row->was_enabled || $row->is_enabled) {
            return;
        }

        event(SecurityEventOccurred::for(
            action: SecurityAuditAction::PolicyWebhookDisabled,
            workspaceId: $workspaceId,
            actor: SecurityActorData::system(),
            targetType: 'security_policy',
            targetId: $workspaceId,
            metadata: ['reason' => 'max_failures', 'failures' => (int) $row->failures],
        ));
    }
}
