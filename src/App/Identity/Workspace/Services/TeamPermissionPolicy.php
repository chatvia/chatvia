<?php

namespace App\Identity\Workspace\Services;

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Membership\Team\Models\Team;
use Domain\Membership\Team\Services\TeamService;

/**
 * What a user's team role lets them do in the dashboard. Permissions are
 * named strings (`agent.setup`, `account.security`, ...) granted to a team
 * role; the user's team is their current team, or their first team when none
 * is selected yet.
 *
 *  - Platform admins hold every permission.
 *  - A team owner holds every permission in their team — but on an agent only
 *    when that agent belongs to their team, never another workspace's agent.
 *  - Everyone else needs the named permission on their team; for an agent
 *    they also need access to that agent.
 *
 * Routes declare the requirement with the `team.permission:<name>` and
 * `agent.permission:<name>` middleware, which ask this class.
 */
final readonly class TeamPermissionPolicy
{
    public function __construct(
        private TeamService $teams,
    ) {}

    /** Whether the user holds a team-level (not agent-scoped) permission. */
    public function allows(User $user, string $permission): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        $team = $this->teamOf($user);

        if ($team === null) {
            return false;
        }

        return $user->ownsTeam($team) || $user->hasTeamPermission($team, $permission);
    }

    /** Whether the user holds a permission on one agent. */
    public function allowsOnAgent(User $user, Agent $agent, string $permission): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        $team = $this->teamOf($user);

        if ($team === null || ! $this->teams->canAccessAgent($user, $team, $agent)) {
            return false;
        }

        return $user->ownsTeam($team) || $user->hasTeamPermission($team, $permission);
    }

    /**
     * Read-only: never writes `current_team_id`, so it is safe on every
     * request.
     */
    private function teamOf(User $user): ?Team
    {
        return $user->currentOrPrimaryTeam();
    }
}
