<?php

namespace App\Identity\Workspace\Concerns;

use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Enums\WorkspaceRole;
use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Models\Workspace;
use App\Identity\Workspace\Models\WorkspaceMembership;
use App\Identity\Workspace\Services\AgentAccessPolicy;
use Domain\Agent\Models\Agent;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;
use LogicException;

/**
 * Where a {@see User} sits in its workspace and what it may reach: the
 * workspace membership (`workspace_user`) and its role, the platform-admin
 * flag, the deactivation switch, and the agent-access shortcuts (rules in
 * {@see AgentAccessPolicy}).
 *
 * {@see Workspace()} is the single resolution path for account-wide data:
 * every staff account (owner or teammate) belongs to exactly one workspace,
 * and workspace data is keyed to it. Chatters are not users: they live in
 * their own table ({@see Chatter}).
 */
trait ResolvesWorkspaceHierarchy
{
    /**
     * The account's place in its workspace.
     *
     * @return HasOne<WorkspaceMembership, $this>
     */
    public function workspaceMembership(): HasOne
    {
        return $this->hasOne(WorkspaceMembership::class);
    }

    /**
     * The workspace this account belongs to. Every staff account has one;
     * the owner's and the teammates' data are all keyed to it.
     */
    public function workspace(): Workspace
    {
        return $this->remember('workspace', fn (): Workspace => $this->workspaceMembership?->workspace
            ?? throw new LogicException("User {$this->id} belongs to no workspace."));
    }

    public function workspaceRole(): ?WorkspaceRole
    {
        return $this->workspaceMembership?->role;
    }

    /** Whether the account owns its workspace (every permission, billing, deletion). */
    public function isWorkspaceOwner(): bool
    {
        return $this->workspaceRole() === WorkspaceRole::Owner;
    }

    /** Whether the account is a teammate: a member of a workspace it does not own. */
    public function isTeammate(): bool
    {
        return $this->workspaceRole() === WorkspaceRole::Member;
    }

    /** Platform staff: may open the admin panel (on its own guard, with MFA). */
    public function isAdmin(): bool
    {
        return (bool) $this->is_admin;
    }

    /**
     * Whether the account has been switched off. A deactivated teammate is
     * rejected by the dashboard and the hosted chat on their next request.
     */
    public function isDeactivated(): bool
    {
        return $this->deactivated_at !== null;
    }

    /**
     * Whether this user may open the control panel: every active staff
     * account does.
     */
    public function canAccessDashboard(): bool
    {
        return ! $this->isDeactivated();
    }

    /**
     * Whether the user may chat with the agent (see {@see AgentAccessPolicy}).
     */
    public function canAccessAgent(Agent $agent): bool
    {
        return app(AgentAccessPolicy::class)->canChat($this, $agent);
    }

    /**
     * Every agent the user may chat with, for the chat agent switcher.
     *
     * @return Collection<int, Agent>
     */
    public function listAccessibleAgents(): Collection
    {
        return app(AgentAccessPolicy::class)->chattableAgents($this);
    }
}
