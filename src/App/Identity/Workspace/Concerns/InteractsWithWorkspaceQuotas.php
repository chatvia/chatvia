<?php

namespace App\Identity\Workspace\Concerns;

use App\Identity\Workspace\Models\User;
use Domain\Billing\Contracts\Entitlements;

/**
 * Quota limits and gates for the {@see User} account. Every reader of a quota
 * limit goes through {@see quotaLimit()} or the `total*Limit()` helpers, which
 * ask the {@see Entitlements} port: with billing on that is the account
 * owner's `user_quotas` row (with a default-package fallback, so a brand-new
 * account with no quota row yet never fatals); with billing off there is no
 * limit.
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
        return max(0, $this->quotaLimit('teams_limit') - $this->teamsCreatedCount());
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
        return $this->teamsCreatedCount() < $this->quotaLimit('teams_limit');
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
     * Effective seat limit — the plan's `workspace_users_limit` plus any
     * active Members add-on quantity mirrored onto `extra_members_limit`.
     * Both team-member invites and chatter creation gate against this.
     */
    public function totalWorkspaceUsersLimit(): int
    {
        return $this->quotaLimit('workspace_users_limit') + (int) ($this->quota?->extra_members_limit ?? 0);
    }

    /**
     * Effective agents limit — the plan's `agents_limit` plus any active
     * extra-agents add-on quantity.
     */
    public function totalAgentsLimit(): int
    {
        return $this->quotaLimit('agents_limit') + (int) ($this->quota?->extra_agents_limit ?? 0);
    }

    /**
     * Effective custom-domains limit — the plan's `custom_domains_limit` plus
     * any active Custom Domain add-on quantity.
     */
    public function totalCustomDomainsLimit(): int
    {
        return $this->quotaLimit('custom_domains_limit') + (int) ($this->quota?->extra_custom_domains_limit ?? 0);
    }

    /**
     * This account's plan limit for one fixed quota, e.g. 'pages_limit' or
     * 'teams_limit' (add-on extras not included — use the `total*Limit()`
     * helpers for those). {@see Entitlements::UNLIMITED} when billing is off.
     */
    public function quotaLimit(string $key): int
    {
        return app(Entitlements::class)->quotaLimit($this, $key);
    }
}
