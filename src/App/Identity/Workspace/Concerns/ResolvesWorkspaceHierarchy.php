<?php

namespace App\Identity\Workspace\Concerns;

use App\Identity\Workspace\Enums\UserType;
use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Services\AgentAccessPolicy;
use Domain\Agent\Models\Agent;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * Where a {@see User} sits in its workspace and what it may reach: the owner
 * link (`workspace_id`) and the accounts under an owner, the account-type
 * checks ({@see UserType}), the deactivation switch, and the agent-access
 * shortcuts (rules in {@see AgentAccessPolicy}).
 *
 * {@see workspace()} is the single resolution path for account-wide data: it
 * returns the owner for a teammate or chatter and the account itself
 * otherwise, so workspace data is always keyed to the owner.
 */
trait ResolvesWorkspaceHierarchy
{
    /**
     * Get the effective user (workspace owner if this is an employee/chatter,
     * otherwise self). Used for resource access like agents, settings, etc.
     */
    public function workspace(): self
    {
        return $this->parentWorkspace ?? $this;
    }

    /**
     * The workspace owner this employee/chatter belongs to.
     *
     * @return BelongsTo<User, $this>
     */
    public function parentWorkspace(): BelongsTo
    {
        return $this->belongsTo(User::class, 'workspace_id');
    }

    /**
     * Check whether this user belongs to a workspace (employee / chatter).
     * Workspace owners and admins return false.
     */
    public function belongsToWorkspace(): bool
    {
        return $this->workspace_id !== null;
    }

    /**
     * Employees (collaborators) this user manages.
     *
     * @return HasMany<User, $this>
     */
    public function managedUsers(): HasMany
    {
        return $this->hasMany(User::class, 'workspace_id')->where('type', UserType::Employee);
    }

    /**
     * Chatters this user has provisioned (via the API).
     *
     * @return HasMany<User, $this>
     */
    public function chatters(): HasMany
    {
        return $this->hasMany(User::class, 'workspace_id')->where('type', UserType::Chatter);
    }

    /**
     * Every account (teammate or chatter) that belongs to this workspace.
     *
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'workspace_id');
    }

    public function isAdmin(): bool
    {
        return $this->type === UserType::Admin;
    }

    public function isWorkspace(): bool
    {
        return in_array($this->type, UserType::workspaceTypes(), true);
    }

    public function isEmployee(): bool
    {
        return $this->type === UserType::Employee;
    }

    public function isChatter(): bool
    {
        return $this->type === UserType::Chatter;
    }

    /**
     * Whether the account has been switched off by a workspace admin. A
     * deactivated chatter is rejected by both widget and hosted-chat auth, so
     * the off-switch takes effect on their next request.
     */
    public function isDeactivated(): bool
    {
        return $this->deactivated_at !== null;
    }

    /**
     * Whether this user may open the control panel. Chatters and deactivated
     * accounts are blocked — active admins, workspace owners, and employees all
     * reach the dashboard.
     */
    public function canAccessDashboard(): bool
    {
        return $this->type !== UserType::Chatter && ! $this->isDeactivated();
    }

    /**
     * Agents a chatter has been explicitly granted access to (via the
     * `agent_user` pivot). Empty for non-chatter types; their agent reach is
     * still resolved through teams + roles via {@see AgentAccessPolicy}.
     *
     * @return BelongsToMany<Agent, $this>
     */
    public function accessibleAgents(): BelongsToMany
    {
        return $this->belongsToMany(Agent::class, 'agent_user');
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
