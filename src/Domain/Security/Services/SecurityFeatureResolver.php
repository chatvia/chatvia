<?php

namespace Domain\Security\Services;

use App\Identity\Workspace\Models\User;
use Domain\Security\Enums\SecurityFeature;
use Domain\Security\Models\WorkspaceSecurityPolicy;

/**
 * Decides whether a user's workspace has the Enterprise Security suite
 * ({@see SecurityFeature::Security}), in this order:
 *
 *  1. platform admins always have it;
 *  2. the per-workspace staff override (`security_override`: true forces it
 *     on, false forces it off), so staff can grant or revoke the suite for any
 *     workspace;
 *  3. otherwise the workspace's live plan capability.
 *
 * The override is read without creating a policy row, so checking the flag
 * (for example in the Inertia share) never writes.
 */
final readonly class SecurityFeatureResolver
{
    public function isActiveFor(?User $user): bool
    {
        if (! $user instanceof User) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        $workspace = $user->workspace();

        // Read through the model so the boolean cast applies (a raw value()
        // can yield Postgres 't'/'f' strings, and (bool) 'f' === true).
        $override = WorkspaceSecurityPolicy::query()
            ->where('workspace_id', $workspace->id)
            ->first(['security_override'])?->security_override;

        return $override ?? $workspace->capabilities->security;
    }
}
