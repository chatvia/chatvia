<?php

namespace App\Identity\Workspace\Services;

use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Agent\Policies\AgentPolicy;
use Domain\Membership\Team\Models\Team;
use Domain\Membership\Team\Services\TeamAgentAccess;
use Illuminate\Support\Collection;

/**
 * The one rule for which agents a staff account may use. It answers two
 * questions:
 *
 *  - {@see canManage()}: may the user work on the agent from the dashboard —
 *    settings, conversations, live events, taking over a chat? This is the
 *    `access-agent` Gate and {@see AgentPolicy}.
 *  - {@see canChat()}: may the user talk to the agent on its hosted chat?
 *    This is `User::canAccessAgent()`, used by the chat sign-in paths.
 *
 * The cases, by account type:
 *
 *  | Account                 | manage                     | chat                    |
 *  |-------------------------|----------------------------|-------------------------|
 *  | Admin (platform staff)  | own agents only            | own agents only         |
 *  | Workspace owner         | own agents only            | own agents only         |
 *  | Teammate (employee)     | agents of their team that  | same as manage          |
 *  |                         | their team role reaches    |                         |
 *
 * Chatters are not staff: they reach only the agents granted to them
 * ({@see Chatter::canAccessAgent()}) and never manage any.
 *
 * A teammate's team is their current team, or their first team when none is
 * selected yet. The agent must belong to that team: owning a team says nothing
 * about agents of another workspace.
 *
 * Platform staff get no reach into other workspaces here: the dashboard and
 * the API sign in without the admin panel's MFA, so cross-workspace access
 * stays inside the Filament panel on the `admin` guard.
 */
final readonly class AgentAccessPolicy
{
    public function __construct(
        private TeamAgentAccess $agentAccess,
    ) {}

    public function canManage(User $user, Agent $agent): bool
    {
        return match (true) {
            $user->isWorkspaceOwner() => $agent->workspace_id === $user->workspace()->id,
            $user->isTeammate() => $this->teammateReaches($user, $agent),
            default => false,
        };
    }

    public function canChat(User $user, Agent $agent): bool
    {
        return $this->canManage($user, $agent);
    }

    /**
     * Every agent the user may chat with, for the chat agent switcher. Kept in
     * step with {@see canChat()} so the list never shows an agent the switch
     * would then refuse.
     *
     * @return Collection<int, Agent>
     */
    public function chattableAgents(User $user): Collection
    {
        return $this->manageableAgents($user);
    }

    /**
     * Every agent the user may work on from the dashboard. Kept in step with
     * {@see canManage()} so a "Manage agents" list never offers an agent the
     * `can.access.agent` route would then refuse. A workspace account lists its
     * own agents only (staff included).
     *
     * @return Collection<int, Agent>
     */
    public function manageableAgents(User $user): Collection
    {
        if ($user->isWorkspaceOwner()) {
            return $user->workspace()->agents()->get()->toBase();
        }

        $team = $this->teamOf($user);

        return $user->isTeammate() && $team instanceof Team
            ? $this->agentAccess->accessibleAgents($user, $team)->toBase()
            : collect();
    }

    private function teammateReaches(User $user, Agent $agent): bool
    {
        $team = $this->teamOf($user);

        return $team instanceof Team
            && $agent->team_id === $team->id
            && $this->agentAccess->canAccess($user, $team, $agent);
    }

    /**
     * Read-only: never writes `current_team_id`, so it is safe in Gate checks
     * and broadcast channel authorization.
     */
    private function teamOf(User $user): ?Team
    {
        return $user->currentOrPrimaryTeam();
    }
}
