<?php

namespace App\Identity\Workspace\Concerns;

use App\Identity\Workspace\Models\User;
use Domain\Billing\Services\Billing;

/**
 * Team & workspace-user quota gates for the {@see User}
 * account. Reads the account owner's `user_quotas` row (resolved through
 * `workspace()`) and the plan's fixed limits, with a default-package fallback so
 * a brand-new account with no quota row yet never fatals.
 */
trait InteractsWithWorkspaceQuotas
{
    /**
     * Get the number of teams the user has created (owns).
     */
    public function teamsCreatedCount(): int
    {
        return $this->ownedTeams()->count();
    }

    /**
     * Number of team members (employees) the user has invited.
     * Informational only — the quota gate uses workspaceUsersCount().
     */
    public function teamMembersCount(): int
    {
        return $this->managedUsers()->count();
    }

    /**
     * Number of chatters provisioned via the API. Informational only —
     * the quota gate uses workspaceUsersCount().
     */
    public function chattersCount(): int
    {
        return $this->chatters()->count();
    }

    /**
     * Total users belonging to this workspace — employees + chatters
     * combined. This is the figure the per-plan quota gates against.
     */
    public function workspaceUsersCount(): int
    {
        return $this->users()->count();
    }

    /**
     * Get remaining teams the user can create.
     */
    public function remainingTeamsQuota(): int
    {
        return max(0, $this->fixedQuotaLimit('teams_limit') - $this->teamsCreatedCount());
    }

    /**
     * Get remaining workspace users (employees + chatters combined)
     * the user can add under their plan's `workspace_users_limit`.
     */
    public function remainingWorkspaceUsersQuota(): int
    {
        return max(0, $this->totalWorkspaceUsersLimit() - $this->workspaceUsersCount());
    }

    /**
     * Check if the user can create more teams.
     */
    public function canCreateTeam(): bool
    {
        return $this->teamsCreatedCount() < $this->fixedQuotaLimit('teams_limit');
    }

    /**
     * Whether this workspace can add another user — employee or chatter.
     * Both types draw from the same `workspace_users_limit` bucket.
     */
    public function canAddWorkspaceUser(): bool
    {
        return $this->workspaceUsersCount() < $this->totalWorkspaceUsersLimit();
    }

    /**
     * Effective seat limit — the plan's `workspace_users_limit` (with the
     * default-package fallback for accounts that have no `user_quotas` row yet)
     * plus any active Members add-on quantity mirrored onto `extra_members_limit`.
     * Both team-member invites and chatter creation gate against this.
     */
    public function totalWorkspaceUsersLimit(): int
    {
        return $this->fixedQuotaLimit('workspace_users_limit') + (int) ($this->quota?->extra_members_limit ?? 0);
    }

    /**
     * This account's fixed quota limit for $key, falling back to the default
     * package's limit when no `user_quotas` row exists yet (fresh signup before
     * the first plan/webhook lands one). Centralises the null-quota handling
     * that every quota gate needs — without it `canAddWorkspaceUser()` and the
     * `remaining*` helpers would fatal on a brand-new account. The default-
     * package shape is owned by the Billing service, not this model.
     */
    private function fixedQuotaLimit(string $key): int
    {
        return (int) ($this->quota?->{$key} ?? app(Billing::class)->defaultFixedLimit($key));
    }
}
