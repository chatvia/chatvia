<?php

namespace Domain\Membership\Team\Services;

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Membership\Team\Models\Role;
use Domain\Membership\Team\Models\Team;
use Illuminate\Support\Collection;

/**
 * Answers "which agents may this teammate reach in this team?". Platform
 * admins reach every agent; the team owner reaches every agent of their own
 * workspace (never another workspace's); anyone else reaches the agents of the
 * team their team role grants — all of them when the role is the owner role or
 * holds the all-agents meta-permission, otherwise the agents on the role's
 * `agent_role` pivot. This matches App\Identity\Workspace\Services\AgentAccessPolicy,
 * which calls it for teammates.
 *
 * Chatters never come through here: their access is the `agent_user` grant,
 * resolved on the User model. The role lookup goes through the User's
 * memoised `teamRole()`, so checking many agents costs one role query.
 */
class TeamAgentAccess
{
    public function canAccess(User $user, Team $team, Agent $agent): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        // The owner reaches every agent of their own workspace — and only
        // those: owning a team says nothing about another workspace's agents.
        if ($user->ownsTeam($team)) {
            return $agent->user_id === $user->workspace()->id;
        }

        if ($agent->team_id !== $team->id) {
            return false;
        }

        $role = $user->teamRole($team);

        return $role instanceof Role && $this->roleCanAccess($role, $agent);
    }

    public function roleCanAccess(Role $role, Agent $agent): bool
    {
        if ($role->grantsAllAgents()) {
            return true;
        }

        return $role->agents()->where('agents.id', $agent->id)->exists();
    }

    /**
     * Every agent of the team the user may reach.
     *
     * @return Collection<int, Agent>
     */
    public function accessibleAgents(User $user, Team $team): Collection
    {
        if ($user->ownsTeam($team)) {
            return $team->agents;
        }

        $role = $user->teamRole($team);

        if (! $role instanceof Role) {
            return collect();
        }

        return $this->roleAccessibleAgents($role, $team);
    }

    /**
     * Every agent of the team a role may reach.
     *
     * @return Collection<int, Agent>
     */
    public function roleAccessibleAgents(Role $role, Team $team): Collection
    {
        if ($role->grantsAllAgents()) {
            return $team->agents;
        }

        return $role->agents()
            ->whereIn('agents.id', $team->agents()->pluck('id'))
            ->get();
    }
}
