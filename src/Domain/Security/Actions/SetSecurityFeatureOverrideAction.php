<?php

namespace Domain\Security\Actions;

use App\Identity\Workspace\Models\User;
use Domain\Security\Data\SecurityActorData;
use Domain\Security\Enums\SecurityAuditAction;
use Domain\Security\Events\SecurityEventOccurred;
use Domain\Security\Models\WorkspaceSecurityPolicy;

/**
 * Platform-staff entitlement override for the Enterprise Security suite. Sets
 * `WorkspaceSecurityPolicy.security_override` so an admin can force-grant
 * (`true`), force-revoke (`false`), or reset to plan-default (`null`) the whole
 * suite for ANY workspace, independent of its plan. The Pennant `security`
 * feature resolver reads this before falling back to the plan entitlement.
 *
 * Operators run it through the `app:security-override` console command; it
 * stays Request/Auth-free and just records who made the change for the audit
 * trail.
 */
class SetSecurityFeatureOverrideAction
{
    public function execute(User $workspace, ?bool $override, SecurityActorData $actor): WorkspaceSecurityPolicy
    {
        $policy = WorkspaceSecurityPolicy::forWorkspace($workspace->id);
        $from = $policy->security_override;

        if ($from === $override) {
            return $policy;
        }

        $policy->security_override = $override;
        $policy->save();

        event(SecurityEventOccurred::for(
            action: SecurityAuditAction::PolicyChanged,
            workspaceId: $workspace->id,
            actor: $actor,
            targetType: 'security_policy',
            targetId: $workspace->id,
            metadata: ['security_override' => ['from' => $from, 'to' => $override]],
        ));

        return $policy;
    }
}
